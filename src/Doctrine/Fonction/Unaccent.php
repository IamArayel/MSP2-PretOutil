<?php

declare(strict_types=1);

namespace App\Doctrine\Fonction;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Fonction DQL UNACCENT(texte), traduite par la fonction PostgreSQL unaccent() (ticket #109).
 * Déclarée dans config/packages/doctrine.yaml ; extension activée par Version20261125150000.
 */
final class Unaccent extends FunctionNode
{
    private Node|string $texte;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->texte = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'unaccent('.$sqlWalker->walkStringPrimary($this->texte).')';
    }
}
