<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0
//
// SDK-specific tests for flametrench/ids.
//
// This file intentionally covers ONLY behaviors that the language-agnostic
// conformance suite (tests/ConformanceTest.php) cannot express — polymorphic
// inputs (UuidV7 instances) and stateful operations (generate uniqueness
// and time-ordering). Every input/output pair and every rejection case that
// CAN be expressed as fixture data lives in the conformance suite, where it
// is verified identically in every Flametrench SDK.
//
// When adding a new spec-defined behavior, first ask: can it be expressed
// as fixture JSON? If yes, add it to spec/conformance/fixtures/ids/ and
// re-vendor — not here.

declare(strict_types=1);

use Flametrench\Ids\Exceptions\InvalidTypeException;
use Flametrench\Ids\Id;
use Symfony\Component\Uid\UuidV7;

describe('Id::encode() — SDK-specific input shapes', function () {
    it('encodes a UuidV7 instance into wire format', function () {
        $uuid = UuidV7::fromString('0190f2a8-1b3c-7abc-8123-456789abcdef');

        expect(Id::encode('org', $uuid))
            ->toBe('org_0190f2a81b3c7abc8123456789abcdef');
    });
});

describe('Id::generate() — stateful, not expressible as a fixture', function () {
    it('produces a valid ID of the requested type', function (string $type) {
        $id = Id::generate($type);

        expect(Id::isValid($id, $type))->toBeTrue();
    })->with(array_keys(Id::TYPES));

    it('produces sortable IDs (UUIDv7 time ordering)', function () {
        $first = Id::generate('usr');
        usleep(2000);
        $second = Id::generate('usr');

        expect(strcmp($first, $second))->toBeLessThan(0);
    });

    it('produces unique IDs', function () {
        $ids = array_map(fn () => Id::generate('usr'), range(1, 1000));

        expect(array_unique($ids))->toHaveCount(1000);
    });

    it('rejects unregistered type prefixes', function () {
        Id::generate('xyz');
    })->throws(InvalidTypeException::class);
});
