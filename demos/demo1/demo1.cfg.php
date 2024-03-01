<?php
/*
 * This file is part of the orkan/tlc package.
 * Copyright (c) 2022 Orkan <orkans+tlc@gmail.com>
 */
global $baseName;

/* @formatter:off */
return [
	'app_title'   => sprintf( '%s: Orkan/TLC', $baseName ),
	'log_file'    => sprintf( '%s/%s.log', __DIR__, $baseName ),
	'net_retry'   => 1,
	'net_timeout' => 2,
	'cache_name'  => $baseName,
	'cache_keep'  => 20,
];
/* @formatter:on */
