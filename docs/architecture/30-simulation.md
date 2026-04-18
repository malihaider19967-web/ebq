# 30 — The Predictor (Simulation & Ranking Math)

> "If you do X, you will rank at Y." This document is what makes that sentence honest.

Parent: [`00-master-plan.md`](./00-master-plan.md) · Siblings: [`10-database.md`](./10-database.md), [`20-researcher.md`](./20-researcher.md)

---

## 1. What "prediction" means here

We are not predicting the Google algorithm. We are predicting the **minimum content-and-UX bar required to out-compete the current Rank-N competitor**, using facts we already have in `market_benchmarks` and `entity_analysis_metrics`.

This is an honest product pitch because:

- Every input is a measured fact (word count, LCP, entity coverage) — not vibes.
- The formula is deterministic. Same inputs → same output. Auditable.
- We record every prediction and every real outcome (from GSC), then calibrate. Over time the weights stop being guesses.

If we cannot show a measured input for a factor, it does not go in the formula. Period.

---

## 2. The Content-First Difficulty score (CFD)

CFD is a single number in `[0, 1]` per URL. Higher = harder to beat. We compute it for:

- **the client's page** (`CFD_you`)
- **each of the top-N competitors** for the target keyword (`CFD_c1, CFD_c2, …, CFD_cN`)

"Predicted rank" is simply: the first position `i` where `CFD_you ≥ CFD_ci`.

### 2.1 Formula

```
CFD = 0.40 · intent_match
    + 0.35 · topical_depth
    + 0.25 · ux_strength
```

The three components:

| Component | Range | Measured from |
|---|---|---|
| `intent_match` | {0.0, 0.5, 1.0} | Does the page's intent class match the SERP's dominant intent? |
| `topical_depth` | [0, 1] | `|entities(page) ∩ entities(benchmark.top_entities)| / |entities(benchmark.top_entities)|` |
| `ux_strength` | [0, 1] | `1 − clamp((page.lcp_ms − benchmark.median_lcp_ms) / benchmark.median_lcp_ms, -1, 1)` |

The three weights must sum to 1.0. They are stored in a single source of truth:

```php
// app/Support/Audit/CFDWeights.php
return [
    'intent'        => 0.40,
    'topical_depth' => 0.35,
    'ux_strength'   => 0.25,
    'revision'      => '2026.04.1',
];
```

Every `simulation_runs` row records the revision it used, so we can re-score historically when weights change.

### 2.2 Why these three

- **Intent match (40%)** — the single largest ranking determinant in practice. If the SERP is informational and the page is transactional, no amount of word count helps.
- **Topical depth (35%)** — the strongest predictor of informational ranking once intent is matched. This is where spaCy entities shine; we measure real coverage, not keyword density.
- **UX strength (25%)** — Core Web Vitals alone don't win rankings, but they are a tie-breaker. The weight reflects that.

### 2.3 What we deliberately left out (for now)

- **Backlinks.** We don't have credible per-URL backlink data from our own crawl, and buying DR from third parties adds a confound to the honest-prediction claim. Add only when we have first-party data.
- **Freshness / recency.** Tempting, but hard to measure without publish-date parsing that is reliable across sites. Add when we have a crawler-side publish-date extractor with >95% accuracy.
- **Schema.org presence.** It becomes an AEO signal (see [§6](#6-aeo-extension)) — not a core CFD component.

---

## 3. Computing each component

### 3.1 Intent match

Dominant SERP intent is computed once, when we fetch `serp_snapshots`:

```
intent_votes = [classify_intent(result.snippet + result.title) for result in top_10]
dominant_intent = mode(intent_votes)
```

`classify_intent` lives in the Researcher and is a rule-based classifier:

- `transactional`: contains `buy|price|cheap|deal|for sale` AND no `how to|guide|vs`.
- `commercial`: contains `best|top \d|review|vs|comparison`.
- `informational`: contains `how to|what is|why|guide|tutorial` OR none of the above.
- `navigational`: title contains a brand name that matches a known host.

Then:

```
intent_match(page) = 1.0  if page.intent == dominant_intent
                     0.5  if page.intent is adjacent (info↔commercial, commercial↔transactional)
                     0.0  otherwise
```

"Adjacent" lets us not punish commercial pages on informational SERPs too harshly — they do sometimes rank.

### 3.2 Topical depth

```
benchmark_entities = market_benchmarks.top_entities      # entities present on ≥50% of winners
page_entities      = entity_analysis_metrics.entities

covered = |{e in benchmark_entities : e ∈ page_entities}|
topical_depth = covered / |benchmark_entities|
```

Two refinements:

1. **Weight by in_headers.** Benchmark entities present in ≥30% of winners' H2/H3 count **double** — they are the load-bearing topics.
2. **Cap at 1.0.** Covering 120% of benchmark is noise; clamp.

### 3.3 UX strength

```
delta = (page.lcp_ms − benchmark.median_lcp_ms) / benchmark.median_lcp_ms
ux_strength = 1 − clamp(delta, -1, +1)
```

- If the page's LCP is 2× the median → `delta=+1` → `ux_strength=0`.
- If it matches the median → `delta=0` → `ux_strength=1`.
- If it's faster than the median → `delta<0`, clamped at `-1` → `ux_strength=2` → clamped → `1`.

We do *not* reward being 4× faster than the median — diminishing returns, noise floor.

---

## 4. The What-If simulator

The core UX: user moves a slider, rank prediction updates live.

### 4.1 Inputs the slider collects

| Slider | Variable | Unit | Clamp |
|---|---|---|---|
| "Add words" | `delta_words` | integer | [-word_count, +5000] |
| "Improve LCP" | `delta_lcp_ms` | integer | [-3000, +3000] |
| "Cover this entity" | `added_entities` | list<string> | unique, ≤ 30 |
| "Change intent" | `override_intent` | enum | one of the four classes |

### 4.2 How the simulation runs

All in-memory in Laravel; no new Researcher call, no new API call.

```php
public function simulate(CustomPageAudit $audit, SimulationInputs $inputs): SimulationResult
{
    $baseline  = $this->snapshot($audit);               // current metrics
    $simulated = $baseline->withDeltas($inputs);         // immutable clone + deltas
    $benchmark = $this->benchmarks->for($audit->website->niche_slug);

    $cfdYou = $this->cfd->score($simulated, $benchmark);

    $rankPredicted = null;
    foreach ($benchmark->competitorsOrdered() as $i => $competitor) {
        if ($cfdYou >= $competitor->cfd) { $rankPredicted = $i + 1; break; }
    }

    return new SimulationResult(
        baselineCfd:  $this->cfd->score($baseline, $benchmark),
        simulatedCfd: $cfdYou,
        predictedRank: $rankPredicted,
        benchmarkId: $benchmark->id,
    );
}
```

Key invariants:

- `baseline` is never mutated; every delta produces a new immutable object (easy to test, easy to reason about).
- `predictedRank` is **null** when `cfdYou < cfd_of_rank_N` — we refuse to predict ranks past the SERP we measured. Don't fabricate confidence.
- Every call writes to `simulation_runs` so we can calibrate later.

### 4.3 What the UI shows

- **Baseline rank** (before deltas).
- **Predicted rank** (after deltas), or "below top 20".
- A **delta-to-each-competitor** breakdown so the user understands which competitor they'd pass.
- A **"why" panel** that shows which component moved the most (`intent_match: +0.20`, `topical_depth: +0.35`, etc.).

The "why" panel is the product's honesty feature. Don't ship the prediction without it.

---

## 5. Calibration loop — closing the honesty gap

Every prediction is a hypothesis. Every GSC check is the answer key.

### 5.1 Collection

For each `simulation_runs` row we have `predicted_rank`. Nightly, we join against GSC:

```
actual_rank = GSC.avg_position(url, keyword, last_14_days)
error = actual_rank - predicted_rank
```

We land this in `simulation_outcomes(simulation_run_id, actual_rank, error, sampled_at)`.

### 5.2 Offline re-weighting

Once we have ≥2,000 outcomes across ≥50 niches, fit weights:

```python
# Linear regression on:
#   y = actual_rank
#   X = [intent_match, topical_depth, ux_strength]
# solve for CFD weights that minimize MSE, subject to weights sum=1 and ≥0.
```

Output: a new weights revision (`2026.Q3.1`). We don't rewrite history — old `simulation_runs` keep their weights string and can be re-scored on demand.

### 5.3 Guardrails

We roll out new weights under a feature flag:

- Shadow-score every new audit with both old and new weights for 2 weeks.
- Compare MAE (mean absolute rank error) on a held-out set.
- Promote only if new MAE is materially lower on ≥3 niches.

No big-bang weight changes, ever.

---

## 6. AEO extension — answer-engine readiness

Classic blue-link ranking is only half the story in 2026. The same data powers an **AEO score** alongside CFD.

```
AEO = 0.30 · has_short_definition       # ≤40-word answer in first 200 words
    + 0.20 · has_faq_or_howto_jsonld
    + 0.20 · h2_questions_pct
    + 0.15 · has_outbound_citations
    + 0.15 · clear_author_or_date
```

AEO and CFD are **separate** numbers shown side-by-side in the UI. They do not blend. Writers optimizing for AI answer-copying and writers optimizing for classic rankings want to see both.

---

## 7. Cannibalization — using the same math

Cannibalization is CFD run on your own pages:

1. For each `(page_a, page_b)` in your site with `cosine(vector_a, vector_b) > 0.85`, compute `CFD_a` and `CFD_b`.
2. The page with higher **clicks over 28 days** from GSC is the **winner**.
3. The other page is the **loser**, and our recommendation is:
   - If `CFD_loser - CFD_winner > 0.15` → suggest a **re-angle** (loser has stronger content; lean into a different sub-intent).
   - Else → suggest a **301 merge** to the winner.

The logic lives in `app/Support/Audit/CannibalizationResolver.php`.

---

## 8. Implementation plan

Phase 2 sequence (after Researcher MVP ships):

1. `App\Support\Audit\CFD` value object + pure unit tests against saved fixtures.
2. `App\Support\Audit\Snapshot` (immutable metrics container) + `withDeltas()`.
3. `App\Services\SimulationService` that orchestrates the two above.
4. Livewire component for the slider UI on the audit detail page.
5. `simulation_runs` persistence + nightly calibration scaffold (outcomes filled after Phase 3).
6. AEO score — purely additive, ships alongside.

Everything in this file is testable without the Researcher running — the only input is the already-persisted `market_benchmarks` and `entity_analysis_metrics` rows. Write fixture-based tests first, then wire the UI.

---

## 9. What to resist

- **Don't add a weight you can't measure.** If a new signal needs a paid API, ship the data source first, the weight second.
- **Don't let the slider stack deltas indefinitely.** +5000 words + -3000ms LCP is not honest. Clamp each input, and warn when the user hits the clamp.
- **Don't round ranks to hide uncertainty.** Show `"Rank 3–5 (est.)"` when the CFD gap between rank 3 and rank 5 competitors is <0.05. Users trust honesty more than precision.
- **Don't merge CFD and AEO into one score.** They solve different problems. One number hides what you're trading off.
