<?php

namespace FRTest;

require __DIR__ . '/vendor/autoload.php';

define('IDX', __DIR__ . '/index');

// Thanks to http://stackoverflow.com/a/40087891/2877364 by
// http://stackoverflow.com/users/3897214/antony

use ZendSearch\Lucene\Lucene;
use ZendSearch\Lucene\Document;
use ZendSearch\Lucene\Document\Field;
use ZendSearch\Lucene\MultiSearcher;

$index = Lucene::create(IDX); // or use open to update an index
$document = new Document;
$document->addField(Field::Text('Hello','World'));
$index->addDocument($document);

$index=NULL;

$search = Lucene::open(IDX);
echo "<pre>";
print_r($search->find('world'));
echo "</pre>";

// vi: set ts=4 sts=4 sw=4 et ai: //

