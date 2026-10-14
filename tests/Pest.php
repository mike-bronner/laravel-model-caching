<?php

use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

pest()->extend(IntegrationTestCase::class)->in('Feature', 'Integration');

if (method_exists(pest(), 'tia')) {
    pest()->tia()->locally()->baselined();
}
