<?php

namespace App\Services\Rendering;

use InvalidArgumentException;

class TailwindClassMap
{
    private static ?array $allowedClasses = null;

    public function section(array $section): string
    {
        $props = $section['props'] ?? [];

        $classes = [
            'builder-section',
            $this->sectionBackground($props['background'] ?? 'default'),
            $this->sectionPadding($props['padding'] ?? 'md'),
        ];

        return $this->classes($classes);
    }

    public function sectionInner(array $section): string
    {
        $props = $section['props'] ?? [];

        return $this->classes([
            'mx-auto',
            'px-6',
            $this->maxWidth($props['max_width'] ?? 'default'),
            ($props['alignment'] ?? 'left') === 'center' ? 'text-center' : 'text-left',
        ]);
    }

    public function node(array $node): string
    {
        $props = $node['props'] ?? [];

        return $this->classes(match ($node['type'] ?? '') {
            'container' => ['builder-node', $this->containerLayout($props), $this->gap($props['gap'] ?? 'md'), $this->containerBackground($props['background'] ?? 'none'), $this->padding($props['padding'] ?? 'md'), $this->radius($props['radius'] ?? 'md')],
            'stack' => ['builder-node', 'flex', 'flex-col', $this->gap($props['gap'] ?? 'md'), $this->items($props['alignment'] ?? 'left')],
            'grid' => ['builder-node', 'grid', $this->gridCols($props['columns'] ?? 3), $this->gap($props['gap'] ?? 'md')],
            'heading' => ['builder-node', $this->headingSize($props['level'] ?? 2), 'font-semibold', 'tracking-normal', $this->textAlign($props['alignment'] ?? 'left'), $this->textEmphasis($props['emphasis'] ?? 'default')],
            'text' => ['builder-node', $this->textSize($props['size'] ?? 'base'), 'leading-7', $this->textAlign($props['alignment'] ?? 'left'), $this->textEmphasis($props['emphasis'] ?? 'default')],
            'image' => ['builder-node', 'max-w-full', $this->imageFit($props['fit'] ?? 'contain'), $this->radius($props['radius'] ?? 'none')],
            'button' => ['builder-node', 'inline-flex', 'items-center', 'justify-center', 'font-semibold', $this->buttonVariant($props['variant'] ?? 'primary'), $this->buttonSize($props['size'] ?? 'md')],
            'badge' => ['builder-node', 'inline-flex', 'items-center', 'font-medium', 'rounded-full', 'px-3', 'py-1', 'text-sm', $this->tone($props['tone'] ?? 'neutral')],
            'link' => ['builder-node', 'inline-flex', $this->linkEmphasis($props['emphasis'] ?? 'default')],
            'input', 'textarea' => ['builder-node', 'w-full', 'rounded-md', 'border', 'border-neutral-300', 'px-3', 'py-2', 'text-neutral-950'],
            'form_group' => ['builder-node', 'flex', ($props['layout'] ?? 'stacked') === 'inline' ? 'flex-row' : 'flex-col', 'gap-2'],
            'card' => ['builder-node', 'rounded-lg', 'border', 'border-neutral-200', 'bg-white', $this->padding($props['padding'] ?? 'md')],
            'icon' => ['builder-node', 'inline-flex', $this->iconSize($props['size'] ?? 'md'), $this->textEmphasis($props['tone'] === 'accent' ? 'accent' : 'muted')],
            'list' => ['builder-node', ($props['style'] ?? 'bulleted') === 'numbered' ? 'list-decimal' : 'list-disc', 'pl-6', 'space-y-2'],
            'list_item' => ['builder-node'],
            'divider' => ['builder-node', 'border-0', 'border-t', ($props['weight'] ?? 'thin') === 'medium' ? 'border-t-2' : 'border-t', 'border-neutral-200', $this->dividerSpacing($props['spacing'] ?? 'md')],
            'element_instance' => ['builder-node'],
            default => ['builder-node'],
        });
    }

    public function element(string $type, array $props): string
    {
        return $this->classes(match ($type) {
            'primary_button' => ['inline-flex', 'items-center', 'justify-center', 'rounded-md', 'bg-blue-600', 'px-4', 'py-2', 'font-semibold', 'text-white'],
            'secondary_button' => ['inline-flex', 'items-center', 'justify-center', 'rounded-md', 'border', 'border-neutral-300', 'px-4', 'py-2', 'font-semibold', 'text-neutral-950'],
            'pill_badge' => ['inline-flex', 'items-center', 'rounded-full', 'px-3', 'py-1', 'text-sm', 'font-medium', $this->tone($props['tone'] ?? 'accent')],
            'feature_card', 'testimonial_card', 'stat_card' => ['rounded-lg', 'border', 'border-neutral-200', 'bg-white', 'p-6'],
            'nav_link_group' => ['flex', ($props['layout'] ?? 'horizontal') === 'horizontal' ? 'flex-row' : 'flex-col', 'gap-4'],
            'cta_group' => ['flex', 'flex-wrap', 'gap-3', $this->justify($props['alignment'] ?? 'left')],
            default => [''],
        });
    }

    public function classes(array $classes): string
    {
        $classes = collect($classes)
            ->flatMap(fn (string $class): array => preg_split('/\s+/', trim($class)) ?: [])
            ->filter()
            ->values()
            ->all();
        $this->assertAllowed($classes);

        return implode(' ', $classes);
    }

    public function assertAllowed(array $classes): void
    {
        if (! (bool) config('app.debug')) {
            return;
        }

        $allowed = $this->allowedClasses();
        foreach ($classes as $class) {
            if (! isset($allowed[$class])) {
                throw new InvalidArgumentException("Tailwind class [{$class}] is not in the renderer safelist.");
            }
        }
    }

    private function allowedClasses(): array
    {
        if (self::$allowedClasses !== null) {
            return self::$allowedClasses;
        }

        $classes = collect(file(base_path('resources/tailwind/safelist.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->map(fn (string $class): string => trim($class))
            ->reject(fn (string $class): bool => $class === '' || str_starts_with($class, '#'))
            ->values()
            ->all();

        return self::$allowedClasses = array_fill_keys($classes, true);
    }

    private function sectionBackground(string $value): string
    {
        return match ($value) {
            'neutral', 'muted' => 'bg-neutral-50',
            'inverse' => 'bg-neutral-900',
            'accent' => 'bg-blue-50',
            default => 'bg-white',
        };
    }

    private function sectionPadding(string $value): string
    {
        return ['sm' => 'py-8', 'md' => 'py-16', 'lg' => 'py-24', 'xl' => 'py-32'][$value] ?? 'py-16';
    }

    private function maxWidth(string $value): string
    {
        return ['narrow' => 'max-w-3xl', 'default' => 'max-w-5xl', 'wide' => 'max-w-7xl', 'full' => 'max-w-full'][$value] ?? 'max-w-5xl';
    }

    private function containerLayout(array $props): string
    {
        return match ($props['layout'] ?? 'block') {
            'flex_row' => 'flex flex-row',
            'flex_col' => 'flex flex-col',
            default => '',
        };
    }

    private function containerBackground(string $value): string
    {
        return match ($value) {
            'neutral', 'muted' => 'bg-neutral-50',
            'accent' => 'bg-blue-50',
            default => '',
        };
    }

    private function padding(string $value): string
    {
        return ['none' => 'p-0', 'sm' => 'p-3', 'md' => 'p-4', 'lg' => 'p-6'][$value] ?? 'p-4';
    }

    private function gap(string $value): string
    {
        return ['none' => '', 'sm' => 'gap-2', 'md' => 'gap-4', 'lg' => 'gap-6', 'xl' => 'gap-8'][$value] ?? 'gap-4';
    }

    private function gridCols(int $value): string
    {
        return "grid-cols-{$value}";
    }

    private function items(string $value): string
    {
        return ['left' => 'items-start', 'center' => 'items-center', 'right' => 'items-end', 'start' => 'items-start', 'end' => 'items-end', 'stretch' => 'items-stretch'][$value] ?? 'items-start';
    }

    private function justify(string $value): string
    {
        return ['left' => 'justify-start', 'center' => 'justify-center', 'right' => 'justify-end', 'start' => 'justify-start', 'end' => 'justify-end', 'between' => 'justify-between'][$value] ?? 'justify-start';
    }

    private function radius(string $value): string
    {
        return ['none' => 'rounded-none', 'sm' => 'rounded-sm', 'md' => 'rounded-md', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl', 'full' => 'rounded-full'][$value] ?? 'rounded-md';
    }

    private function headingSize(int $level): string
    {
        return [1 => 'text-5xl', 2 => 'text-3xl', 3 => 'text-2xl', 4 => 'text-xl'][$level] ?? 'text-3xl';
    }

    private function textSize(string $value): string
    {
        return ['xs' => 'text-xs', 'sm' => 'text-sm', 'base' => 'text-base', 'lg' => 'text-lg', 'xl' => 'text-xl'][$value] ?? 'text-base';
    }

    private function textAlign(string $value): string
    {
        return "text-{$value}";
    }

    private function textEmphasis(string $value): string
    {
        return match ($value) {
            'muted' => 'text-neutral-600',
            'accent' => 'text-blue-600',
            default => 'text-neutral-950',
        };
    }

    private function imageFit(string $value): string
    {
        return $value === 'cover' ? 'object-cover' : 'object-contain';
    }

    private function buttonVariant(string $value): string
    {
        return match ($value) {
            'secondary' => 'border border-neutral-300 text-neutral-950',
            'ghost' => 'text-neutral-950 hover:underline',
            default => 'bg-blue-600 text-white',
        };
    }

    private function buttonSize(string $value): string
    {
        return $value === 'sm' ? 'px-3 py-1 text-sm' : 'px-4 py-2 text-base';
    }

    private function tone(string $value): string
    {
        return match ($value) {
            'positive' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            'warning' => 'bg-amber-50 text-amber-700 border border-amber-200',
            'info' => 'bg-cyan-50 text-cyan-700 border border-cyan-200',
            'accent' => 'bg-blue-50 text-blue-600 border border-blue-200',
            default => 'bg-neutral-100 text-neutral-700 border border-neutral-200',
        };
    }

    private function linkEmphasis(string $value): string
    {
        return $value === 'muted' ? 'text-neutral-600' : 'text-blue-600 underline';
    }

    private function iconSize(string $value): string
    {
        return ['sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-xl', 'xl' => 'text-2xl'][$value] ?? 'text-base';
    }

    private function dividerSpacing(string $value): string
    {
        return ['sm' => 'my-4', 'md' => 'my-8', 'lg' => 'my-12'][$value] ?? 'my-8';
    }
}
