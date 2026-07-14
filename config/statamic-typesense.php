<?php

return [

    // If you make this higher it will use more memory
    // but be quicker to update large numbers of documents.
    'insert_chunk_size' => 100,

    // Typesense may reject individual documents during a bulk import
    // (e.g. schema type mismatches) while the import itself succeeds.
    // By default these failures are logged as a warning. Set this to
    // true to throw an exception instead.
    'throw_on_import_failure' => false,

];
