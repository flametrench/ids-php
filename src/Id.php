<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Ids;

use Flametrench\Ids\Exceptions\InvalidIdException;
use Flametrench\Ids\Exceptions\InvalidTypeException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * Flametrench wire-format ID encoder and decoder.
 *
 * Flametrench public identifiers combine a short type prefix with a compact
 * UUIDv7 representation separated by an underscore:
 *
 *     usr_0190f2a81b3c7abc8123456789abcdef
 *
 * Storage format: UUIDv7 in canonical hyphenated form.
 * Wire format: "{type}_{hex}" where hex is the UUID with hyphens stripped.
 *
 * The underscore separator is chosen over hyphen because hyphens are
 * ambiguous at the end of a URL (e.g., in email subject lines, auto-linked
 * text), and because UUID canonical form already uses hyphens.
 *
 * Type prefixes are lowercase ASCII, 2 to 6 characters, and correspond to
 * resource kinds in the Flametrench specification. Implementations must not
 * invent prefixes outside the specification.
 */
final class Id
{
    /**
     * Registered type prefixes for Flametrench v0.1.
     *
     * Keep this list synchronized with the Flametrench specification's
     * reserved prefix registry at:
     * https://github.com/flametrench/spec/blob/main/docs/ids.md
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'usr' => 'user',
        'org' => 'organization',
        'mem' => 'membership',
        'inv' => 'invitation',
        'ses' => 'session',
        'cred' => 'credential',
        'tup' => 'authorization_tuple',
        // v0.2 — Proposed (ADR 0008)
        'mfa' => 'mfa_factor',
        // v0.2 — Proposed (ADR 0012)
        'shr' => 'share_token',
    ];

    private function __construct() {}

    /**
     * Encode a type and UUID into Flametrench wire format.
     *
     * @param string $type A registered type prefix (see self::TYPES).
     * @param string|UuidV7 $uuid A canonical UUID string or UuidV7 instance.
     * @return string The wire-format ID (e.g. "usr_0190f2a81b3c7abc8123456789abcdef").
     *
     * @throws InvalidTypeException If the type prefix is not registered.
     * @throws InvalidIdException If the UUID is not a valid UUIDv7.
     */
    public static function encode(string $type, string|UuidV7 $uuid): string
    {
        self::assertType($type);

        if (is_string($uuid)) {
            if (! Uuid::isValid($uuid)) {
                throw new InvalidIdException("Value is not a valid UUID: {$uuid}");
            }
            $uuid = UuidV7::fromString($uuid);
        }

        $hex = str_replace('-', '', (string) $uuid);

        return "{$type}_{$hex}";
    }

    /**
     * Decode a Flametrench wire-format ID into its type and canonical UUID.
     *
     * @param string $id The wire-format ID to decode.
     * @return array{type: string, uuid: string}
     *
     * @throws InvalidIdException If the ID is malformed.
     * @throws InvalidTypeException If the type prefix is not registered.
     */
    public static function decode(string $id): array
    {
        $parts = explode('_', $id, 2);

        if (count($parts) !== 2) {
            throw new InvalidIdException("ID missing type separator: {$id}");
        }

        [$type, $hex] = $parts;

        self::assertType($type);

        if (preg_match('/^[0-9a-f]{32}$/', $hex) !== 1) {
            throw new InvalidIdException("ID payload is not 32 lowercase hex characters: {$id}");
        }

        // Version nibble (13th hex char) must be 1-8. This rejects the Nil UUID
        // (v0) and Max UUID (v15/f), which pass format validators but are not
        // meaningful identifiers.
        if (strpos('12345678', $hex[12]) === false) {
            throw new InvalidIdException("ID payload is not a valid UUID: {$id}");
        }

        $canonical = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );

        return [
            'type' => $type,
            'uuid' => $canonical,
        ];
    }

    /**
     * Decode a Flametrench wire-format ID without checking the registered-type set.
     *
     * Use this for backend storage adapters that need to convert wire-format
     * object IDs to canonical UUIDs without knowing the application's
     * domain types in advance — e.g., when an authz tuple has
     * objectType: "proj" and objectId: "proj_0190f2a8...".
     *
     * Validates wire-format shape (separator, 32-char lowercase hex, version
     * nibble 1–8). Does NOT consult the registered TYPES set. See
     * spec/docs/ids.md.
     *
     * @return array{type: string, uuid: string}
     *
     * @throws InvalidIdException If the ID's structure is malformed. Never
     *                            throws InvalidTypeException.
     */
    public static function decodeAny(string $id): array
    {
        $parts = explode('_', $id, 2);

        if (count($parts) !== 2) {
            throw new InvalidIdException("ID missing type separator: {$id}");
        }

        [$type, $hex] = $parts;

        if ($type === '') {
            throw new InvalidIdException("ID has empty type prefix: {$id}");
        }

        if (preg_match('/^[0-9a-f]{32}$/', $hex) !== 1) {
            throw new InvalidIdException("ID payload is not 32 lowercase hex characters: {$id}");
        }

        if (strpos('12345678', $hex[12]) === false) {
            throw new InvalidIdException("ID payload is not a valid UUID: {$id}");
        }

        $canonical = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );

        return [
            'type' => $type,
            'uuid' => $canonical,
        ];
    }

    /**
     * Check whether a string is a valid Flametrench wire-format ID.
     *
     * Optionally asserts that the ID is of a specific type.
     *
     * @param string $id The string to check.
     * @param string|null $expectedType Optional type prefix to match against.
     */
    public static function isValid(string $id, ?string $expectedType = null): bool
    {
        try {
            $decoded = self::decode($id);
        } catch (InvalidIdException | InvalidTypeException) {
            return false;
        }

        if ($expectedType !== null && $decoded['type'] !== $expectedType) {
            return false;
        }

        return true;
    }

    /**
     * Extract the type prefix from a wire-format ID without full decoding.
     *
     * @throws InvalidIdException If the ID is malformed.
     * @throws InvalidTypeException If the type prefix is not registered.
     */
    public static function typeOf(string $id): string
    {
        return self::decode($id)['type'];
    }

    /**
     * Predicate counterpart to {@see decodeAny()}. Returns true for any
     * well-formed wire-format ID regardless of registry membership.
     *
     * Use this when validating input from external systems that may
     * legitimately reference application-defined object types.
     */
    public static function isValidShape(string $id): bool
    {
        try {
            self::decodeAny($id);
            return true;
        } catch (InvalidIdException) {
            return false;
        }
    }

    /**
     * Generate a fresh wire-format ID of the given type.
     *
     * Uses UUIDv7 so generated IDs are sortable by creation time.
     *
     * @throws InvalidTypeException If the type prefix is not registered.
     */
    public static function generate(string $type): string
    {
        self::assertType($type);

        return self::encode($type, new UuidV7());
    }

    /**
     * @throws InvalidTypeException
     */
    private static function assertType(string $type): void
    {
        if (! array_key_exists($type, self::TYPES)) {
            $registered = implode(', ', array_keys(self::TYPES));
            throw new InvalidTypeException(
                "Unregistered type prefix: '{$type}'. Registered prefixes: {$registered}."
            );
        }
    }
}
