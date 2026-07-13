<?php

namespace App\View\Components;

use Illuminate\View\Component;

class StatCard extends Component
{
    public string $cardId;

    public function __construct(
        public string $label = '',
        public string $value = '',
        public string $subLabel = '',
        public string $icon = 'bi-bar-chart-line',
        public string $iconBg = 'bg-gray-100',
        public string $iconColor = 'text-gray-500',
        public array $sparkData = [],
        public array $sparkLabels = [],  // e.g. ['Jan','Feb',...] — shown in tooltip
        public string $sparkColor = '#8b5cf6',
        public string $size = 'md',   // sm | md | lg
        public ?string $href = null,    // navigate on click
        public ?string $modal = null,    // open modal ID on click
        public ?string $onClick = null,    // raw JS expression on click
        public ?string $trend = null,    // "+12%" displayed below value
        public bool $trendUp = true,
        public ?string $refreshEvent = null,  // window event name for live updates
        string $cardId = '',
    ) {
        $this->cardId = $cardId ?: 'sc-'.substr(md5($label.$value.uniqid()), 0, 8);
    }

    public function isClickable(): bool
    {
        return $this->href !== null || $this->modal !== null || $this->onClick !== null;
    }

    public function clickHandler(): string
    {
        if ($this->href) {
            return "window.location='{$this->href}'";
        }
        if ($this->modal) {
            return "document.getElementById('{$this->modal}')?.dispatchEvent(new Event('show-modal'))";
        }
        if ($this->onClick) {
            return $this->onClick;
        }

        return '';
    }

    public function sizeClasses(): array
    {
        return match ($this->size) {
            'sm' => ['pad' => 'px-4 py-3',   'value' => 'text-2xl', 'label' => 'text-[9px]',  'sub' => 'text-[11px]', 'icon' => 'w-9 h-9 text-base'],
            'lg' => ['pad' => 'px-6 py-5',   'value' => 'text-5xl', 'label' => 'text-[11px]', 'sub' => 'text-sm',     'icon' => 'w-13 h-13 text-xl'],
            default => ['pad' => 'px-5 pt-5 pb-2', 'value' => 'text-4xl', 'label' => 'text-[10px]', 'sub' => 'text-xs', 'icon' => 'w-11 h-11 text-lg'],
        };
    }

    public function render()
    {
        return view('components.stat-card');
    }
}
