<?php

namespace App\Services\Generation;

use App\Models\Page;
use App\Models\Project;
use App\Services\Generation\Stages\PromptBuilder;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\StructuredRequest;
use Illuminate\Support\Str;

class SitePagePlanner
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly PromptBuilder $prompts,
    ) {}

    /**
     * @return array{summary: string, pages: array<int, array{name: string, slug: string, brief: string, source: string, source_label: string, reason: string, confidence: float}>}
     */
    public function plan(Page $sourcePage, Project $project, string $provider, ?string $model = null, ?string $apiKey = null): array
    {
        $provider = trim($provider) !== '' ? trim($provider) : (string) config('llm.default_provider', 'anthropic');

        $response = $this->provider->sendStructured(new StructuredRequest(
            stage: 'site_page_planner',
            provider: $provider,
            model: $model ?: (string) config("llm.providers.{$provider}.models.section_generator"),
            systemPrompt: $this->prompts->system('site_page_planner'),
            userPrompt: $this->userPrompt($sourcePage, $project),
            toolName: 'submit_site_page_plan',
            schema: $this->schema(),
            context: [
                'project_id' => $project->id,
                'source_page_id' => $sourcePage->id,
                'source_page_name' => $sourcePage->name,
            ],
            maxTokens: 3000,
            apiKey: $apiKey,
        ));

        return $this->normalize($response->output);
    }

    private function userPrompt(Page $sourcePage, Project $project): string
    {
        return trim(implode("\n\n", [
            "Project name: {$project->name}",
            "Source page name: {$sourcePage->name}",
            "Source page prompt:\n".((string) ($sourcePage->prompt ?? '') !== '' ? $sourcePage->prompt : '(no prompt)'),
            "Existing project pages:\n".$this->json($this->existingPages($project)),
            "Menu and same-site link candidates extracted locally:\n".$this->json($this->menuLinks((string) ($sourcePage->html_source ?? ''))),
            "Source page HTML excerpt:\n".$this->sourceExcerpt((string) ($sourcePage->html_source ?? '')),
        ]));
    }

    /**
     * @return array<int, array{name: string, status: string, has_html: bool}>
     */
    private function existingPages(Project $project): array
    {
        return $project->pages()
            ->oldest()
            ->get()
            ->map(fn (Page $page): array => [
                'name' => $page->name,
                'status' => $page->status,
                'has_html' => trim((string) ($page->html_source ?? '')) !== '',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, href: string, context: string}>
     */
    private function menuLinks(string $html): array
    {
        $links = [];
        $dom = new \DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));
            $label = trim(preg_replace('/\s+/', ' ', (string) $anchor->textContent) ?? '');

            if ($href === '' || $label === '' || $this->isIgnoredHref($href)) {
                continue;
            }

            $links[] = [
                'label' => Str::limit($label, 80, ''),
                'href' => Str::limit($href, 160, ''),
                'context' => $this->linkContext($anchor),
            ];
        }

        return collect($links)
            ->unique(fn (array $link): string => strtolower($link['label'].'|'.$link['href']))
            ->take(20)
            ->values()
            ->all();
    }

    private function isIgnoredHref(string $href): bool
    {
        $lower = strtolower($href);

        return str_starts_with($lower, 'mailto:')
            || str_starts_with($lower, 'tel:')
            || str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'http://')
            || str_starts_with($lower, 'https://')
            || $lower === '#';
    }

    private function linkContext(\DOMNode $node): string
    {
        while ($node->parentNode instanceof \DOMNode) {
            $node = $node->parentNode;

            if ($node instanceof \DOMElement && in_array(strtolower($node->tagName), ['nav', 'header', 'footer'], true)) {
                return strtolower($node->tagName);
            }
        }

        return 'body';
    }

    private function sourceExcerpt(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '(source page is empty)';
        }

        if (mb_strlen($html, 'UTF-8') <= 18000) {
            return $html;
        }

        return mb_substr($html, 0, 9000, 'UTF-8')
            ."\n\n<!-- source page truncated for planner -->\n\n"
            .mb_substr($html, -7000, null, 'UTF-8');
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'pages'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'pages' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 12,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'slug', 'brief', 'source', 'source_label', 'reason', 'confidence'],
                        'properties' => [
                            'name' => ['type' => 'string', 'minLength' => 1],
                            'slug' => ['type' => 'string', 'minLength' => 1],
                            'brief' => ['type' => 'string', 'minLength' => 1],
                            'source' => ['type' => 'string', 'enum' => ['menu', 'planner', 'user']],
                            'source_label' => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{summary: string, pages: array<int, array{name: string, slug: string, brief: string, source: string, source_label: string, reason: string, confidence: float}>}
     */
    private function normalize(array $output): array
    {
        $usedSlugs = [];
        $pages = collect($output['pages'] ?? [])
            ->filter(fn (mixed $page): bool => is_array($page))
            ->map(function (array $page) use (&$usedSlugs): array {
                $name = trim((string) ($page['name'] ?? ''));
                $slug = Str::slug((string) ($page['slug'] ?? $name)) ?: Str::slug($name);

                if ($name === '' || $slug === '') {
                    return [];
                }

                $baseSlug = $slug;
                $suffix = 2;

                while (isset($usedSlugs[$slug])) {
                    $slug = "{$baseSlug}-{$suffix}";
                    $suffix++;
                }

                $usedSlugs[$slug] = true;

                return [
                    'name' => Str::limit($name, 160, ''),
                    'slug' => Str::limit($slug, 160, ''),
                    'brief' => trim((string) ($page['brief'] ?? 'Create a focused page for '.$name.'.')),
                    'source' => in_array(($page['source'] ?? null), ['menu', 'planner', 'user'], true) ? (string) $page['source'] : 'planner',
                    'source_label' => Str::limit(trim((string) ($page['source_label'] ?? '')), 160, ''),
                    'reason' => Str::limit(trim((string) ($page['reason'] ?? 'Useful page for this site.')), 300, ''),
                    'confidence' => max(0.0, min(1.0, (float) ($page['confidence'] ?? 0.7))),
                ];
            })
            ->filter(fn (array $page): bool => $page !== [])
            ->values()
            ->all();

        return [
            'summary' => trim((string) ($output['summary'] ?? 'Proposed pages for this site.')),
            'pages' => $pages,
        ];
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]';
    }
}
