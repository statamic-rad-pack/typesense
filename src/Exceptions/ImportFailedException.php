<?php

namespace StatamicRadPack\Typesense\Exceptions;

use Exception;

class ImportFailedException extends Exception
{
    public function __construct(protected string $index, protected array $failures)
    {
        parent::__construct(sprintf('%d document(s) failed to import into the [%s] index.', count($failures), $index));
    }

    public function index(): string
    {
        return $this->index;
    }

    public function failures(): array
    {
        return $this->failures;
    }
}
