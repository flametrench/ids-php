<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0
//
// Flametrench v0.1 conformance suite — PHP harness.
//
// This file exercises the IDs capability against the fixture corpus vendored
// from github.com/flametrench/spec/conformance/fixtures/ids/. The fixtures
// in tests/conformance/fixtures/ are a snapshot; the drift-check CI job
// ensures they match the upstream spec repo.
//
// Every test name is "[{fixture_id}] {description}" so failures point
// directly at a spec-linked fixture. Do not modify test behavior here —
// if a fixture needs to change, change it in the spec repo and re-vendor.

declare(strict_types=1);

use Flametrench\Ids\Exceptions\InvalidIdException;
use Flametrench\Ids\Exceptions\InvalidTypeException;
use Flametrench\Ids\Id;

const FIXTURES_DIR = __DIR__ . '/conformance/fixtures';

/**
 * Load a fixture JSON file and return its tests array along with metadata.
 *
 * @return array{tests: array, capability: string, operation: string, conformance_level: string}
 */
function loadFixture(string $relativePath): array
{
    $path = FIXTURES_DIR . '/' . $relativePath;
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    return $data;
}

/**
 * Map a spec-level error name (as it appears in fixtures) to the concrete
 * PHP exception class. Spec error names are language-neutral; each SDK maps
 * them to idiomatic local types. PHP's convention appends "Exception".
 */
function errorClassForSpecName(string $name): string
{
    return match ($name) {
        'InvalidIdError' => InvalidIdException::class,
        'InvalidTypeError' => InvalidTypeException::class,
        default => throw new RuntimeException("Unknown spec error name: {$name}"),
    };
}

// ─── ids.encode ───

$encode = loadFixture('ids/encode.json');
describe("Conformance · {$encode['capability']}.{$encode['operation']} [{$encode['conformance_level']}]", function () use ($encode) {
    foreach ($encode['tests'] as $test) {
        it("[{$test['id']}] {$test['description']}", function () use ($test) {
            $input = $test['input'];
            if (isset($test['expected']['error'])) {
                $expectedClass = errorClassForSpecName($test['expected']['error']);
                expect(fn () => Id::encode($input['type'], $input['uuid']))
                    ->toThrow($expectedClass);
            } else {
                expect(Id::encode($input['type'], $input['uuid']))
                    ->toBe($test['expected']['result']);
            }
        });
    }
});

// ─── ids.decode (positive + round-trip) ───

$decode = loadFixture('ids/decode.json');
describe("Conformance · {$decode['capability']}.{$decode['operation']} [{$decode['conformance_level']}] · positive", function () use ($decode) {
    foreach ($decode['tests'] as $test) {
        it("[{$test['id']}] {$test['description']}", function () use ($test) {
            $result = Id::decode($test['input']['id']);
            expect($result)->toBe($test['expected']['result']);
        });
    }
});

// ─── ids.decode (rejection) ───

$decodeReject = loadFixture('ids/decode-reject.json');
describe("Conformance · {$decodeReject['capability']}.{$decodeReject['operation']} [{$decodeReject['conformance_level']}] · rejection", function () use ($decodeReject) {
    foreach ($decodeReject['tests'] as $test) {
        it("[{$test['id']}] {$test['description']}", function () use ($test) {
            $expectedClass = errorClassForSpecName($test['expected']['error']);
            expect(fn () => Id::decode($test['input']['id']))
                ->toThrow($expectedClass);
        });
    }
});

// ─── ids.is_valid ───

$isValid = loadFixture('ids/is-valid.json');
describe("Conformance · {$isValid['capability']}.{$isValid['operation']} [{$isValid['conformance_level']}]", function () use ($isValid) {
    foreach ($isValid['tests'] as $test) {
        it("[{$test['id']}] {$test['description']}", function () use ($test) {
            $input = $test['input'];
            $result = isset($input['expected_type'])
                ? Id::isValid($input['id'], $input['expected_type'])
                : Id::isValid($input['id']);
            expect($result)->toBe($test['expected']['result']);
        });
    }
});

// ─── ids.type_of ───

$typeOf = loadFixture('ids/type-of.json');
describe("Conformance · {$typeOf['capability']}.{$typeOf['operation']} [{$typeOf['conformance_level']}]", function () use ($typeOf) {
    foreach ($typeOf['tests'] as $test) {
        it("[{$test['id']}] {$test['description']}", function () use ($test) {
            if (isset($test['expected']['error'])) {
                $expectedClass = errorClassForSpecName($test['expected']['error']);
                expect(fn () => Id::typeOf($test['input']['id']))
                    ->toThrow($expectedClass);
            } else {
                expect(Id::typeOf($test['input']['id']))->toBe($test['expected']['result']);
            }
        });
    }
});
