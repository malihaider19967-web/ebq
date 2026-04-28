<x-marketing.page
    title="Refund Policy — EBQ"
    description="EBQ refund policy. 30-day money-back guarantee, pro-rata refunds for downtime, cancel anytime."
>
    <article class="mx-auto max-w-3xl px-6 pb-20 pt-14 lg:px-8 lg:pb-28 lg:pt-20">
        <header>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-200">Legal</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white sm:text-5xl">Refund Policy</h1>
            <p class="mt-3 text-sm text-slate-400">Last updated: {{ \Illuminate\Support\Carbon::create(2026, 4, 28)->format('F j, Y') }}</p>
            <p class="mt-6 text-base leading-7 text-slate-200">
                We want you to be confident trying EBQ. Here's exactly how refunds work — fair, clear, no fine print.
            </p>
        </header>

        <div class="prose prose-invert mt-10 max-w-none text-slate-200 prose-headings:text-white prose-h2:mt-12 prose-h2:text-2xl prose-h3:mt-8 prose-h3:text-lg prose-a:text-indigo-200 hover:prose-a:text-indigo-100 prose-strong:text-white">

            <h2>1. 14-day free trial — no charge</h2>
            <p>Every paid plan starts with a 14-day free trial. Cancel anytime during the trial and your card is never charged. There's nothing to refund because nothing was paid.</p>

            <h2>2. 30-day money-back guarantee</h2>
            <p>If you upgraded to a paid plan and decide EBQ isn't for you within <strong>30 days of your first paid charge</strong>, email <a href="mailto:billing@ebq.io">billing@ebq.io</a> and we will refund your payment in full. No questionnaire, no friction.</p>
            <p>This applies to your <em>first</em> paid period only — monthly or annual. Subsequent renewals are not covered by the 30-day guarantee but may be eligible for a pro-rata refund (see below).</p>

            <h2>3. Cancel anytime</h2>
            <p>You can cancel your subscription from <strong>Settings → Billing</strong> at any time. Cancellation:</p>
            <ul>
                <li>Stops future renewals.</li>
                <li>Lets you keep using the plan until the end of the current paid period.</li>
                <li>Does not retroactively refund the period you already paid for, except under the 30-day guarantee or the conditions in §4.</li>
            </ul>

            <h2>4. Pro-rata refunds for service issues</h2>
            <p>If we materially break the Service for an extended period (significant feature removal on a paid plan, prolonged outage beyond what's reasonable for SaaS, billing errors), we will refund the affected portion of your subscription on a pro-rata basis. Email <a href="mailto:billing@ebq.io">billing@ebq.io</a> with the affected dates and we'll work it out.</p>

            <h2>5. Annual plans</h2>
            <p>If you cancel an annual plan after the 30-day guarantee window:</p>
            <ul>
                <li>Service continues until the annual period ends.</li>
                <li>No automatic refund of the unused months.</li>
                <li>If you're switching to monthly within the same year, contact <a href="mailto:billing@ebq.io">billing@ebq.io</a> and we'll arrange a pro-rata credit toward the monthly plan.</li>
            </ul>

            <h2>6. Add-ons and extras</h2>
            <p>Add-on purchases (extra websites, extra keyword slots, extra audit credits) are billed in the same cycle as your base plan. They're refundable under the same 30-day window as the base plan; outside that, unused credits roll forward but aren't refunded in cash.</p>

            <h2>7. What's not refundable</h2>
            <ul>
                <li>Renewal charges after the 30-day window for first-time customers.</li>
                <li>Add-on credits already consumed (e.g. audits already run, AI generations already returned, indexing-API submissions already sent).</li>
                <li>Sales tax / VAT collected on behalf of tax authorities — those are returned through your local refund mechanism, not by us directly.</li>
                <li>Charges resulting from misuse of the Service in breach of our <a href="{{ route('terms-conditions') }}">Terms of Service</a>.</li>
            </ul>

            <h2>8. How to request a refund</h2>
            <p>Email <a href="mailto:billing@ebq.io">billing@ebq.io</a> from the email associated with your EBQ account. Include:</p>
            <ul>
                <li>Your workspace name (or the email used to sign up).</li>
                <li>The charge date / invoice number you want refunded.</li>
                <li>(Optional) A short note about why — useful feedback for us, not a gating question.</li>
            </ul>
            <p>We respond within 2 business days and process approved refunds within 5–10 business days, depending on your bank. Refunds go back to the original payment method.</p>

            <h2>9. Chargebacks</h2>
            <p>Please contact us before initiating a chargeback — almost every billing question is faster to resolve directly. Disputes filed without contacting us first may result in account suspension while we work with the payment processor.</p>

            <h2>10. Changes to this policy</h2>
            <p>We may update this policy. Material changes are posted on this page and notified to active subscribers by email at least 14 days before they take effect. Refund eligibility for charges made <em>before</em> the change is governed by the policy in force at the time of the charge.</p>

            <h2>11. Contact</h2>
            <p>Billing questions: <a href="mailto:billing@ebq.io">billing@ebq.io</a>. We're a small team and we read every message.</p>
        </div>
    </article>
</x-marketing.page>
