<?php

namespace CustomerManagementFramework\CustomerList\Filter;

use BackendToolkit\Listing\Filter\AbstractFilter;
use BackendToolkit\Listing\OnCreateQueryFilterInterface;
use Pimcore\Model\Object\Listing as CoreListing;
use SearchQueryParser\Lexer;
use SearchQueryParser\Parser;
use SearchQueryParser\QueryBuilder\ZendDbSelect;

class SearchQuery extends AbstractFilter implements OnCreateQueryFilterInterface
{
    /**
     * @var array
     */
    protected $fields;

    /**
     * @var string
     */
    protected $parsedQuery;

    /**
     * @param array $fields
     * @param string $query
     */
    public function __construct(array $fields, $query)
    {
        $this->fields      = $fields;
        $this->query       = $query;
        $this->parsedQuery = $this->parseQuery($query);
    }

    /**
     * @inheritDoc
     */
    public function applyOnCreateQuery(CoreListing\Concrete $listing, \Zend_Db_Select $query)
    {
        $queryBuilder = new ZendDbSelect($this->fields);
        $queryBuilder->processQuery($query, $this->parsedQuery);
    }

    /**
     * @param string $queryString
     * @return \SearchQueryParser\Part\Query
     */
    protected function parseQuery($queryString)
    {
        $lexer  = new Lexer();
        $parser = new Parser();

        $tokens = $lexer->lex($queryString);
        $query  = $parser->parse($tokens);

        return $query;
    }
}