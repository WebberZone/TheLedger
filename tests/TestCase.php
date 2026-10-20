<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Per-test installer state (marker, setup code, lock), so no test sees
     * another's installation and none touches storage/app/install.
     */
    protected string $installState;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installState = sys_get_temp_dir().'/ledger-install-state-'.Str::random(10);
        config(['ledger.install.state_path' => $this->installState]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->installState);

        parent::tearDown();
    }
}
