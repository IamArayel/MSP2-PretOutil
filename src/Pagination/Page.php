<?php

declare(strict_types=1);

namespace App\Pagination;

/**
 * Une page de résultats, indépendante de Doctrine : les contrôleurs et les vues
 * n'ont pas à connaître le Paginator de l'ORM.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param list<T> $elements
     */
    public function __construct(
        public array $elements,
        public int $total,
        public int $numero,
        public int $parPage,
    ) {
    }

    public function nombreDePages(): int
    {
        return max(1, (int) ceil($this->total / $this->parPage));
    }

    public function aUnePagePrecedente(): bool
    {
        return $this->numero > 1;
    }

    public function aUnePageSuivante(): bool
    {
        return $this->numero < $this->nombreDePages();
    }
}
