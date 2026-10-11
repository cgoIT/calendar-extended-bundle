<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle\Tests\DependencyInjection;

use Cgoit\CalendarExtendedBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = $this->process([]);

        $this->assertSame(365, $config['max_repeat_count']);
        $this->assertSame(['title', 'location_name', 'location_str', 'location_plz'], $config['filter_fields']);
        $this->assertSame(250, $config['exceptions']['max_count']);
        $this->assertSame(7, $config['exceptions']['move_days']);
        $this->assertSame(['from' => '00:00', 'to' => '23:59', 'interval' => 15], $config['exceptions']['move_times']);
    }

    public function testOverridesValues(): void
    {
        $config = $this->process([
            'max_repeat_count' => 10,
            'filter_fields' => ['title'],
            'exceptions' => ['max_count' => 5, 'move_days' => 14, 'move_times' => ['interval' => 30]],
        ]);

        $this->assertSame(10, $config['max_repeat_count']);
        $this->assertSame(['title'], $config['filter_fields']);
        $this->assertSame(5, $config['exceptions']['max_count']);
        $this->assertSame(14, $config['exceptions']['move_days']);
        $this->assertSame(30, $config['exceptions']['move_times']['interval']);
        $this->assertSame('00:00', $config['exceptions']['move_times']['from']);
    }

    public function testRejectsMaxRepeatCountBelowOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['max_repeat_count' => 0]);
    }

    public function testRejectsMaxExceptionsCountBelowOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['exceptions' => ['max_count' => 0]]);
    }

    public function testRejectsMoveTimesIntervalBelowOne(): void
    {
        // An interval of 0 would lead to an endless loop when the time options are generated
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['exceptions' => ['move_times' => ['interval' => 0]]]);
    }

    #[DataProvider('invalidTimes')]
    public function testRejectsInvalidMoveTimes(string $key, mixed $time): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['exceptions' => ['move_times' => [$key => $time]]]);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidTimes(): iterable
    {
        yield 'from without minutes' => ['from', '8'];
        yield 'from with seconds' => ['from', '08:00:00'];
        yield 'from out of range' => ['from', '24:00'];
        yield 'to with invalid minutes' => ['to', '12:60'];
        yield 'to text' => ['to', 'noon'];
        yield 'to empty' => ['to', ''];
    }

    public function testAcceptsValidMoveTimes(): void
    {
        $config = $this->process(['exceptions' => ['move_times' => ['from' => '06:30', 'to' => '22:00']]]);

        $this->assertSame('06:30', $config['exceptions']['move_times']['from']);
        $this->assertSame('22:00', $config['exceptions']['move_times']['to']);
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
