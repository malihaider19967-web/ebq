<?php

namespace App\Services;

use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * Code-defined registry of AI Studio tools. No DB table — tools are
 * PHP classes under `App\AiTools\Tools\{Category}\{Tool}.php`.
 *
 * The list is intentionally explicit (not auto-discovered via filesystem
 * scan) so:
 *   - missing classes fail fast at boot, not at runtime
 *   - the canonical tool order is reviewable in version control
 *   - it's trivial to feature-flag a tool by removing one line
 *
 * Each entry holds a class name; the registry instantiates lazily via
 * the container so tools can request constructor dependencies.
 */
class AiToolRegistry
{
    /**
     * Master list, in display order. Adding a tool = appending a class
     * here. Removing = deleting the line.
     *
     * @var list<class-string<AiTool>>
     */
    private const TOOL_CLASSES = [
        // Research (10)
        \App\AiTools\Tools\Research\TopicResearch::class,
        \App\AiTools\Tools\Research\KeywordResearch::class,
        \App\AiTools\Tools\Research\KeywordSuggestions::class,
        \App\AiTools\Tools\Research\KeywordVariations::class,
        \App\AiTools\Tools\Research\QuestionsPaa::class,
        \App\AiTools\Tools\Research\RelatedSearches::class,
        \App\AiTools\Tools\Research\ContentBrief::class,
        \App\AiTools\Tools\Research\SeoTitle::class,
        \App\AiTools\Tools\Research\SeoDescription::class,
        \App\AiTools\Tools\Research\SeoMeta::class,

        // Writing (11)
        \App\AiTools\Tools\Writing\BlogPostWizard::class,
        \App\AiTools\Tools\Writing\BlogIdeaGenerator::class,
        \App\AiTools\Tools\Writing\OutlineGenerator::class,
        \App\AiTools\Tools\Writing\SectionGenerator::class,
        \App\AiTools\Tools\Writing\ParagraphGenerator::class,
        \App\AiTools\Tools\Writing\IntroGenerator::class,
        \App\AiTools\Tools\Writing\ConclusionGenerator::class,
        \App\AiTools\Tools\Writing\RewriteContent::class,
        \App\AiTools\Tools\Writing\ExpandContent::class,
        \App\AiTools\Tools\Writing\ShortenContent::class,
        \App\AiTools\Tools\Writing\SimplifyContent::class,
        \App\AiTools\Tools\Writing\ContinueWriting::class,

        // Improvement (7)
        \App\AiTools\Tools\Improvement\FixGrammar::class,
        \App\AiTools\Tools\Improvement\ImproveReadability::class,
        \App\AiTools\Tools\Improvement\ChangeTone::class,
        \App\AiTools\Tools\Improvement\Summarizer::class,
        \App\AiTools\Tools\Improvement\KeyPoints::class,
        \App\AiTools\Tools\Improvement\FaqGenerator::class,
        \App\AiTools\Tools\Improvement\HeadingGenerator::class,

        // Marketing (6)
        \App\AiTools\Tools\Marketing\AdCopy::class,
        \App\AiTools\Tools\Marketing\SocialPost::class,
        \App\AiTools\Tools\Marketing\Tweet::class,
        \App\AiTools\Tools\Marketing\EmailCopy::class,
        \App\AiTools\Tools\Marketing\CtaGenerator::class,
        \App\AiTools\Tools\Marketing\ProductDescriptionMkt::class,

        // Ecommerce (5)
        \App\AiTools\Tools\Ecommerce\ProductTitle::class,
        \App\AiTools\Tools\Ecommerce\ProductDescriptionShort::class,
        \App\AiTools\Tools\Ecommerce\ProductDescriptionLong::class,
        \App\AiTools\Tools\Ecommerce\ProductFeatures::class,
        \App\AiTools\Tools\Ecommerce\ProductFaqs::class,

        // Media (4)
        \App\AiTools\Tools\Media\AltText::class,
        \App\AiTools\Tools\Media\InternalLinkSuggestions::class,
        \App\AiTools\Tools\Media\ExternalLinkSuggestions::class,
        \App\AiTools\Tools\Media\SchemaSuggestions::class,

        // Misc (4)
        \App\AiTools\Tools\Misc\Rephraser::class,
        \App\AiTools\Tools\Misc\SentenceGenerator::class,
        \App\AiTools\Tools\Misc\ListGenerator::class,
        \App\AiTools\Tools\Misc\Definition::class,
    ];

    /** @var array<string, AiTool> */
    private array $byId = [];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return Collection<int, AiTool>
     */
    public function all(): Collection
    {
        return collect(self::TOOL_CLASSES)
            ->map(fn (string $cls) => $this->resolve($cls))
            ->values();
    }

    public function has(string $id): bool
    {
        return $this->find($id) !== null;
    }

    public function find(string $id): ?AiTool
    {
        if (isset($this->byId[$id])) {
            return $this->byId[$id];
        }
        foreach (self::TOOL_CLASSES as $cls) {
            $tool = $this->resolve($cls);
            if ($tool->meta()->id === $id) {
                return $this->byId[$id] = $tool;
            }
        }
        return null;
    }

    /**
     * @return Collection<int, AiTool>
     */
    public function inCategory(string $category): Collection
    {
        return $this->all()->filter(fn (AiTool $t) => $t->meta()->category === $category)->values();
    }

    /**
     * @return Collection<int, AiTool>
     */
    public function forSurface(string $surface): Collection
    {
        return $this->all()->filter(fn (AiTool $t) => in_array($surface, $t->meta()->surfaces, true))->values();
    }

    /**
     * Public catalog payload — what the plugin sees.
     *
     * @return array{categories: list<array<string, mixed>>, tools: list<array<string, mixed>>}
     */
    public function catalog(): array
    {
        $tools = $this->all()->map(fn (AiTool $t) => $t->meta()->toArray())->all();
        $categories = [];
        foreach (Categories::ORDERED as $cat) {
            $categories[] = [
                'id' => $cat,
                'label' => Categories::LABELS[$cat] ?? $cat,
                'description' => Categories::DESCRIPTIONS[$cat] ?? '',
                'tool_count' => count(array_filter($tools, static fn (array $t) => $t['category'] === $cat)),
            ];
        }
        return [
            'categories' => $categories,
            'tools' => $tools,
        ];
    }

    private function resolve(string $cls): AiTool
    {
        /** @var AiTool $instance */
        $instance = $this->container->make($cls);
        return $instance;
    }
}
