<?php

/**
 * Stubs PHPStan pour l'extension PHP smbclient (pecl/smbclient).
 */

/** @return mixed */
function smbclient_state_new() {}

function smbclient_state_init(mixed $state, string $workgroup = '', string $username = '', string $password = ''): bool {}

function smbclient_connect(mixed $state, string $server): bool {}

/**
 * @return array<string, array{attr: int, size: int, mtime: int}>|false
 */
function smbclient_ls(mixed $state, string $uri): array|false {}

/** @return mixed */
function smbclient_open(mixed $state, string $uri, string $mode) {}

function smbclient_read(mixed $state, mixed $file, int $bytes): string|false {}

function smbclient_write(mixed $state, mixed $file, string $data, int $length = -1): int|false {}

function smbclient_close(mixed $state, mixed $file): bool {}

function smbclient_eof(mixed $state, mixed $file): bool {}

/**
 * @return array<string, mixed>|false
 */
function smbclient_stat(mixed $state, string $uri): array|false {}

function smbclient_mkdir(mixed $state, string $uri): bool {}

function smbclient_state_free(mixed $state): bool {}
