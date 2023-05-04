<?php

declare(strict_types=1);

namespace OpenTelemetry\Tests\Unit\SDK\Logs;

use AssertWell\PHPUnitGlobalState\EnvironmentVariables;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\LogRecordProcessorFactory;
use OpenTelemetry\SDK\Logs\Processor\BatchLogsProcessor;
use OpenTelemetry\SDK\Logs\Processor\MultiLogsProcessor;
use OpenTelemetry\SDK\Logs\Processor\NoopLogsProcessor;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogsProcessor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \OpenTelemetry\SDK\Logs\LogRecordProcessorFactory
 */
class LogRecordProcessorFactoryTest extends TestCase
{
    use EnvironmentVariables;

    public function tearDown(): void
    {
        $this->restoreEnvironmentVariables();
    }

    /**
     * @dataProvider exporterProvider
     */
    public function test_create($name, $expected): void
    {
        $exporter = $this->createMock(LogRecordExporterInterface::class);
        $this->setEnvironmentVariable('OTEL_PHP_LOGS_PROCESSOR', $name);
        $processor = (new LogRecordProcessorFactory())->create($exporter);

        $this->assertInstanceOf($expected, $processor);
    }

    public static function exporterProvider(): array
    {
        return [
            ['batch', BatchLogsProcessor::class],
            ['simple', SimpleLogsProcessor::class],
            ['noop', NoopLogsProcessor::class],
            ['none', NoopLogsProcessor::class],
        ];
    }

    public function test_create_invalid(): void
    {
        $this->setEnvironmentVariable('OTEL_PHP_LOGS_PROCESSOR', 'baz');
        $this->expectException(\InvalidArgumentException::class);

        (new LogRecordProcessorFactory())->create($this->createMock(LogRecordExporterInterface::class));
    }

    public function test_create_multiple(): void
    {
        $this->setEnvironmentVariable('OTEL_PHP_LOGS_PROCESSOR', 'batch,simple');
        $processor = (new LogRecordProcessorFactory())->create($this->createMock(LogRecordExporterInterface::class));

        $this->assertInstanceOf(MultiLogsProcessor::class, $processor);
    }

    public function test_create_from_environment(): void
    {
        $expected = [
            'maxQueueSize' => 10,
            'scheduledDelayNanos' => 2 * 1_000_000,
            'maxExportBatchSize' => 1,
        ];
        $this->setEnvironmentVariable(Variables::OTEL_PHP_LOGS_PROCESSOR, 'batch');
        $this->setEnvironmentVariable(Variables::OTEL_BLRP_MAX_QUEUE_SIZE, 10);
        $this->setEnvironmentVariable(Variables::OTEL_BLRP_SCHEDULE_DELAY, 2);
        $this->setEnvironmentVariable(Variables::OTEL_BLRP_EXPORT_TIMEOUT, 3);
        $this->setEnvironmentVariable(Variables::OTEL_BLRP_MAX_EXPORT_BATCH_SIZE, 1);

        $processor = (new LogRecordProcessorFactory())->create($this->createMock(LogRecordExporterInterface::class));
        $this->assertInstanceOf(BatchLogsProcessor::class, $processor);

        $reflection = new ReflectionClass($processor);
        foreach ($expected as $propertyName => $value) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $this->assertSame($value, $property->getValue($processor));
        }
    }
}
