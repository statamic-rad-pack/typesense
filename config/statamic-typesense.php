<?php

return [

    // If you make this higher it will use more memory
    // but be quicker to update large numbers of documents.
    'insert_chunk_size' => 100,

    // Use multiSearch via POST for search requests.
    // Typesense limits GET request parameter size for security
    // reasons, so if you have many or long search parameters,
    // you should enable this.
    'use_multi_search' => env('STATAMIC_TYPESENSE_MULTISEARCH', false)

];
