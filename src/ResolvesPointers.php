<?php

namespace SimpleSoftwareIO\SqsDisk;

use Illuminate\Support\Arr;
use Illuminate\Filesystem\FilesystemAdapter;

trait ResolvesPointers
{
    /**
     * Resolves the job payload pointer.
     *
     * @return string|null
     */
    protected function resolvePointer(): ?string
    {
        return json_decode($this->job['Body'])->pointer ?? null;
    }

    /**
     * Resolves the configured queue disk that stores large payloads.
     *
     * @return FilesystemAdapter
     */
    protected function resolveDisk(): FilesystemAdapter
    {
        return $this->container->make('filesystem')->disk(Arr::get($this->diskOptions, 'disk'));
    }
}
