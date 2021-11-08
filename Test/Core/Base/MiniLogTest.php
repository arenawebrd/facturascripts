<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Test\Core\Base;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Model\LogMessage;
use FacturaScripts\Test\Core\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

/**
 * Description of MiniLogTest
 *
 * @author Carlos Carlos Garcia Gomez <carlos@facturascripts.com>
 * @covers \FacturaScripts\Core\Base\MiniLog
 */
final class MiniLogTest extends TestCase
{
    use LogErrorsTrait;

    const CHANNEL = 'test';

    public function testCritical()
    {
        MiniLog::clear(self::CHANNEL);
        $logger = new MiniLog(self::CHANNEL);

        $message = 'test-critical';
        $context = ['test-key' => 'FAIL', 'other-key' => 'other', 'more' => 1234];
        $logger->critical($message, $context);

        $data = MiniLog::read(self::CHANNEL);
        $this->assertNotEmpty($data, 'log-empty');
        $this->assertEquals(1, count($data), 'more-than-one-message');
        $this->assertEquals($message, $data[0]['message']);
        $this->assertNotEmpty($data[0]['context'], 'empty-context');
        $this->assertGreaterThanOrEqual(3, count($data[0]['context']), 'bad-context');
        $this->assertEquals('other', $data[0]['context']['other-key'], 'bad-context-key');
        $this->assertEquals('critical', $data[0]['level']);
        $this->assertEquals(self::CHANNEL, $data[0]['channel']);
    }

    public function testError()
    {
        MiniLog::clear(self::CHANNEL);
        $logger = new MiniLog(self::CHANNEL);

        $message = 'test-error';
        $context = ['test-key' => '78687687681111'];
        $logger->error($message, $context);

        $data = MiniLog::read(self::CHANNEL);
        $this->assertNotEmpty($data, 'log-empty');
        $this->assertEquals(1, count($data), 'more-than-one-message');
        $this->assertEquals($message, $data[0]['message']);
        $this->assertNotEmpty($data[0]['context'], 'empty-context');
        $this->assertEquals('error', $data[0]['level']);
        $this->assertEquals(self::CHANNEL, $data[0]['channel']);
    }

    public function testInfo()
    {
        MiniLog::clear(self::CHANNEL);
        $logger = new MiniLog(self::CHANNEL);

        $message = 'test-info';
        $context = ['test-key' => '78686867'];
        $logger->info($message, $context);

        $data = MiniLog::read(self::CHANNEL);
        $this->assertNotEmpty($data, 'log-empty');
        $this->assertEquals(1, count($data), 'more-than-one-message');
        $this->assertEquals($message, $data[0]['message']);
        $this->assertNotEmpty($data[0]['context'], 'empty-context');
        $this->assertEquals('info', $data[0]['level']);
        $this->assertEquals(self::CHANNEL, $data[0]['channel']);
    }

    public function testNotice()
    {
        MiniLog::clear(self::CHANNEL);
        $logger = new MiniLog(self::CHANNEL);

        $message = 'test-notice';
        $context = ['test-key' => '3232324'];
        $logger->notice($message, $context);

        $data = MiniLog::read(self::CHANNEL);
        $this->assertNotEmpty($data, 'log-empty');
        $this->assertEquals(1, count($data), 'more-than-one-message');
        $this->assertEquals($message, $data[0]['message']);
        $this->assertNotEmpty($data[0]['context'], 'empty-context');
        $this->assertEquals('notice', $data[0]['level']);
        $this->assertEquals(self::CHANNEL, $data[0]['channel']);
    }

    public function testWarning()
    {
        MiniLog::clear(self::CHANNEL);
        $logger = new MiniLog(self::CHANNEL);

        $message = 'test-war';
        $context = ['test-key' => 'war'];
        $logger->warning($message, $context);

        $data = MiniLog::read(self::CHANNEL);
        $this->assertNotEmpty($data, 'log-empty');
        $this->assertEquals(1, count($data), 'more-than-one-message');
        $this->assertEquals($message, $data[0]['message']);
        $this->assertNotEmpty($data[0]['context'], 'empty-context');
        $this->assertEquals('warning', $data[0]['level']);
        $this->assertEquals(self::CHANNEL, $data[0]['channel']);
    }

    public function testClearChannel()
    {
        $defaultLogger = new MiniLog();
        $defaultLogger->info('test-default');
        $data = MiniLog::read(MiniLog::DEFAULT_CHANNEL);
        $this->assertNotEmpty($data, 'default-log-empty');
        $this->assertEquals(MiniLog::DEFAULT_CHANNEL, $data[0]['channel']);

        $logger = new MiniLog(self::CHANNEL);
        $logger->info('test');
        $this->assertNotEmpty(MiniLog::read(self::CHANNEL), 'log-empty');

        MiniLog::clear(self::CHANNEL);
        $this->assertEmpty(MiniLog::read(self::CHANNEL), 'log-not-empty');
        $this->assertNotEmpty(MiniLog::read(MiniLog::DEFAULT_CHANNEL), 'default-channel-log-empty');
        $this->assertNotEmpty(MiniLog::read(), 'all-channels-log-empty');
    }

    public function testClearAllChannels()
    {
        $defaultLogger = new MiniLog();
        $defaultLogger->info('test-default');

        $logger = new MiniLog(self::CHANNEL);
        $logger->info('test');

        MiniLog::clear();
        $this->assertEmpty(MiniLog::read(self::CHANNEL), 'log-not-empty');
        $this->assertEmpty(MiniLog::read(), 'default-channel-log-not-empty');
    }

    public function testMessageCount()
    {
        $defaultLogger = new MiniLog();
        $defaultLogger->info('test');

        $logger = new MiniLog(self::CHANNEL);
        $logger->info('test');
        $logger->info('test');

        $data = MiniLog::read(self::CHANNEL);
        $this->assertNotEmpty($data, 'log-empty');
        $this->assertEquals(1, count($data), 'more-than-one-message');
        $this->assertEquals('test', $data[0]['message']);
        $this->assertEquals(2, $data[0]['count']);
    }

    public function testRead()
    {
        MiniLog::clear(self::CHANNEL);
        $logger = new MiniLog(self::CHANNEL);
        $logger->info('test');

        $this->assertNotEmpty(MiniLog::read(), 'full-log-empty');
        $this->assertNotEmpty(MiniLog::read(self::CHANNEL), 'channel-log-empty');
        $this->assertNotEmpty(MiniLog::read(self::CHANNEL, ['info']), 'channel-level-log-empty');
        $this->assertEmpty(MiniLog::read(self::CHANNEL, ['error']), 'channel-level-error-not-empty');
    }

    public function testSave()
    {
        // crear data
        MiniLog::clear();
        $logModel = new LogMessage();
        $where = [new DataBaseWhere('channel', self::CHANNEL)];
        foreach ($logModel->all($where, [], 0, 0) as $item) {
            $item->delete();
        }

        $logger = new MiniLog(self::CHANNEL);
        $logger->error('test', ['more' => 'one']);
        $this->assertTrue(MiniLog::save(), 'cant-save-log');
        $this->assertEmpty(MiniLog::read(self::CHANNEL), 'log-not-empty');

        // verify data from database
        $items = $logModel->all($where, [], 0, 0);
        $this->assertNotEmpty($items, 'log-item-not-found-in-db');
        $this->assertEquals(1, count($items), 'more-than-one-log-item-in-db');
        $this->assertEquals('test', $items[0]->message);
        $this->assertEquals('error', $items[0]->level);
        $this->assertEquals(self::CHANNEL, $items[0]->channel);

        // check context
        $context = $items[0]->context();
        $this->assertIsArray($context, 'db-context-is-not-an-array');
        $this->assertEquals('one', $context['more'], 'bad-db-context-key');
    }

    protected function tearDown()
    {
        $this->logErrors();
    }
}
