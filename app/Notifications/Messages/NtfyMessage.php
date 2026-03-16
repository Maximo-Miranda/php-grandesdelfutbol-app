<?php

namespace App\Notifications\Messages;

class NtfyMessage
{
    protected ?string $title = null;

    protected int $priority = 3;

    protected ?string $tags = null;

    protected ?string $clickUrl = null;

    protected bool $useMarkdown = false;

    /** @var array<int, array{action: string, label: string, url: string}> */
    protected array $actions = [];

    public function __construct(protected string $body) {}

    public static function create(string $body): self
    {
        return new self($body);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function tags(string $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function click(string $url): self
    {
        $this->clickUrl = $url;

        return $this;
    }

    public function markdown(): self
    {
        $this->useMarkdown = true;

        return $this;
    }

    public function action(string $label, string $url): self
    {
        $this->actions[] = [
            'action' => 'view',
            'label' => $label,
            'url' => $url,
        ];

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'message' => $this->body,
            'priority' => $this->priority,
            'title' => $this->title,
            'tags' => $this->tags ? explode(',', $this->tags) : null,
            'click' => $this->clickUrl,
            'markdown' => $this->useMarkdown ?: null,
            'actions' => $this->actions ?: null,
        ], fn ($value) => $value !== null);
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
