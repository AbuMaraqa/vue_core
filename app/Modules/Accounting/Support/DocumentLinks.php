<?php

namespace App\Modules\Accounting\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * How to name and open the document behind a journal entry. Accounting does
 * not know the other modules, so each registers its documents from its
 * service provider:
 *
 * ```php
 * $links->register(Invoice::class, fn (Invoice $invoice): array => [
 *     'label' => $invoice->type->label(),
 *     'url' => route("{$invoice->type->routeName()}.show", $invoice),
 * ]);
 * ```
 */
class DocumentLinks
{
    /**
     * @var array<string, Closure>
     */
    protected array $resolvers = [];

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  Closure(TModel): array{label: string, url: string|null}  $resolver
     */
    public function register(string $model, Closure $resolver): void
    {
        $this->resolvers[(new $model)->getMorphClass()] = $resolver;
    }

    /**
     * The document's type label and page, or null when no module registered it.
     *
     * @return array{label: string, url: string|null}|null
     */
    public function for(Model $document): ?array
    {
        $resolver = $this->resolvers[$document->getMorphClass()] ?? null;

        if ($resolver === null) {
            return null;
        }

        /** @var array{label: string, url: string|null} $link */
        $link = $resolver($document);

        return $link;
    }
}
