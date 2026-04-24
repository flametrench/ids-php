<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

use Flametrench\Ids\Exceptions\InvalidIdException;
use Flametrench\Ids\Exceptions\InvalidTypeException;
use Flametrench\Ids\Id;
use Symfony\Component\Uid\UuidV7;

describe('Id::encode()', function () {
    it('encodes a canonical UUID string into wire format', function () {
        $encoded = Id::encode('usr', '0190f2a8-1b3c-7abc-8123-456789abcdef');

        expect($encoded)->toBe('usr_0190f2a81b3c7abc8123456789abcdef');
    });

    it('encodes a UuidV7 instance into wire format', function () {
        $uuid = UuidV7::fromString('0190f2a8-1b3c-7abc-8123-456789abcdef');

        $encoded = Id::encode('org', $uuid);

        expect($encoded)->toBe('org_0190f2a81b3c7abc8123456789abcdef');
    });

    it('rejects unregistered type prefixes', function () {
        Id::encode('xyz', '0190f2a8-1b3c-7abc-8123-456789abcdef');
    })->throws(InvalidTypeException::class);

    it('rejects malformed UUID strings', function () {
        Id::encode('usr', 'not-a-uuid');
    })->throws(InvalidIdException::class);

    it('produces IDs of the expected length for all registered types', function (string $type) {
        $id = Id::encode($type, '0190f2a8-1b3c-7abc-8123-456789abcdef');
        $expectedLength = strlen($type) + 1 + 32;

        expect(strlen($id))->toBe($expectedLength);
    })->with(array_keys(Id::TYPES));
});

describe('Id::decode()', function () {
    it('decodes a wire-format ID into type and canonical UUID', function () {
        $decoded = Id::decode('usr_0190f2a81b3c7abc8123456789abcdef');

        expect($decoded)->toBe([
            'type' => 'usr',
            'uuid' => '0190f2a8-1b3c-7abc-8123-456789abcdef',
        ]);
    });

    it('is the inverse of encode', function (string $type) {
        $original = '0190f2a8-1b3c-7abc-8123-456789abcdef';

        $encoded = Id::encode($type, $original);
        $decoded = Id::decode($encoded);

        expect($decoded['type'])->toBe($type);
        expect($decoded['uuid'])->toBe($original);
    })->with(array_keys(Id::TYPES));

    it('rejects IDs without a type separator', function () {
        Id::decode('usr0190f2a81b3c7abc8123456789abcdef');
    })->throws(InvalidIdException::class, 'missing type separator');

    it('rejects unregistered type prefixes', function () {
        Id::decode('xyz_0190f2a81b3c7abc8123456789abcdef');
    })->throws(InvalidTypeException::class);

    it('rejects payloads that are not 32 hex characters', function (string $malformed) {
        Id::decode($malformed);
    })->with([
        'too short' => 'usr_0190f2a8',
        'too long' => 'usr_0190f2a81b3c7abc8123456789abcdef0000',
        'non-hex' => 'usr_0190f2a81b3c7abc8123456789abcdeg0',
        'empty payload' => 'usr_',
    ])->throws(InvalidIdException::class);

    it('rejects payloads that parse to invalid UUIDs', function () {
        Id::decode('usr_ffffffffffffffffffffffffffffffff');
    })->throws(InvalidIdException::class);
});

describe('Id::isValid()', function () {
    it('returns true for well-formed IDs', function () {
        expect(Id::isValid('usr_0190f2a81b3c7abc8123456789abcdef'))->toBeTrue();
    });

    it('returns false for malformed IDs', function (string $malformed) {
        expect(Id::isValid($malformed))->toBeFalse();
    })->with([
        'no separator' => 'usr0190f2a81b3c7abc8123456789abcdef',
        'unknown type' => 'xyz_0190f2a81b3c7abc8123456789abcdef',
        'short payload' => 'usr_deadbeef',
        'empty string' => '',
        'garbage' => 'not an id at all',
    ]);

    it('validates against an expected type when provided', function () {
        $id = 'usr_0190f2a81b3c7abc8123456789abcdef';

        expect(Id::isValid($id, 'usr'))->toBeTrue();
        expect(Id::isValid($id, 'org'))->toBeFalse();
    });
});

describe('Id::typeOf()', function () {
    it('returns the type prefix of a valid ID', function () {
        expect(Id::typeOf('org_0190f2a81b3c7abc8123456789abcdef'))->toBe('org');
    });

    it('throws on invalid IDs', function () {
        Id::typeOf('garbage');
    })->throws(InvalidIdException::class);
});

describe('Id::generate()', function () {
    it('produces a valid ID of the requested type', function (string $type) {
        $id = Id::generate($type);

        expect(Id::isValid($id, $type))->toBeTrue();
    })->with(array_keys(Id::TYPES));

    it('produces sortable IDs (UUIDv7 time ordering)', function () {
        $first = Id::generate('usr');
        usleep(2000); // 2ms gap to ensure timestamp advancement
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

describe('round-trip properties', function () {
    it('encode then decode recovers the original UUID for all registered types', function (string $type) {
        $originals = [
            '0190f2a8-1b3c-7abc-8123-456789abcdef',
            '01000000-0000-7000-8000-000000000000',
            '01ffffff-ffff-7fff-bfff-ffffffffffff',
        ];

        foreach ($originals as $original) {
            $encoded = Id::encode($type, $original);
            $decoded = Id::decode($encoded);

            expect($decoded['uuid'])->toBe($original);
            expect($decoded['type'])->toBe($type);
        }
    })->with(array_keys(Id::TYPES));
});
