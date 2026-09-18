<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Dane referencyjne sieją się raz na proces, nie raz na test.
     *
     * `RefreshDatabase` migruje raz na proces, a potem owija każdy test
     * transakcją. Siew w `setUp` trafiał do tej transakcji i wracał do
     * kosza po każdej metodzie, więc te same słowniki powstawały ponad
     * pięćset razy w jednym przebiegu. `$seed` przenosi go tam, gdzie
     * stoi migracja: przed transakcję, raz.
     *
     * Sieje się `DatabaseSeeder`, czyli seedery rdzeniowe plus testowe.
     * Deweloperskie (`Dev\*`) zostają poza tym — test, który ich
     * potrzebuje, woła je u siebie i ma je wtedy tylko u siebie.
     */
    protected bool $seed = true;
}
