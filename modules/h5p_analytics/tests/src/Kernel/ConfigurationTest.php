<?php

namespace Drupal\Tests\h5p_analytics\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test description.
 *
 * @group h5p_analytics
 */
class ConfigurationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['h5p_analytics'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('h5p_analytics');
  }

  /**
   * Tests initial configuration after install.
   */
  public function testConfiguration() {
    $config = $this->config('h5p_analytics.settings');
    self::assertSame(null, $config->get('xapi_endpoint'));
    self::assertSame(null, $config->get('key'));
    self::assertSame(null, $config->get('secret'));
    self::assertSame(100, $config->get('batch_size'));
    self::assertSame(45.0, $config->get('timeout'));
  }

}
