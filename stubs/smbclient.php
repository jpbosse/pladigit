<?php

/**
 * Stubs PHPStan pour l'extension PHP smbclient.
 * Ces fonctions sont fournies par l'extension pecl/smbclient en production.
 */

function smbclient_state_new(): mixed {}
function smbclient_state_init(mixed $state, string $workgroup = '', string $username = '', string $password = ''): bool {}
function smbclient_connect(mixed $state, string $server): bool {}
function smbclient_ls(mixed $state, string $uri): array|false {}
function smbclient_open(mixed $state, string $uri, string $mode): mixed {}
function smbclient_read(mixed $state, mixed $file, int $bytes): string|false {}
function smbclient_write(mixed $state, mixed $file, string $data, int $length = -1): int|false {}
function smbclient_close(mixed $state, mixed $file): bool {}
function smbclient_eof(mixed $state, mixed $file): bool {}
function smbclient_stat(mixed $state, string $uri): array|false {}
function smbclient_mkdir(mixed $state, string $uri): bool {}
function smbclient_state_free(mixed $state): bool {}
