<?php

namespace Drupal\Tests\h5p_analytics\Kernel;

use Drupal\h5p_analytics\LrsServiceInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test description.
 *
 * @group h5p_analytics
 */
class HooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['h5p_analytics'];

  /**
   * Tests hook_help().
   */
  public function testHelp() {
    $this->assertEquals(h5p_analytics_help('help.page.h5p_analytics', \Drupal::routeMatch()), '<h3>About</h3><p>H5P xAPI LRS integration module collects any statements from both within the internal and external (embed) pages with H5P content and sends those to the LRS.</p><p>Please visit the <a href="/admin/reports/h5p_analytics/statistics">LRS statistics page</a> to see some data for statements being sent and problematic HTTP requests.</p>');
  }

  /**
   * Tests hook_page_attachments().
   */
  public function testPageAttachments() {
    $attachments = [];
    h5p_analytics_page_attachments($attachments);
    $this->assertEquals($attachments['#attached']['library'][0], 'h5p_analytics/behaviour');
    $this->assertEquals($attachments['#attached']['drupalSettings']['H5PAnalytics']['endpointUrl'], '/h5p_analytics/xapi');
  }

  /**
   * Tests hook_h5p_scripts_alter().
   */
  public function testH5pScriptsAlter() {
    $scripts = [];

    h5p_analytics_h5p_scripts_alter($scripts, [], 'external');

    $this->assertTrue(is_object($scripts[0]));
    $this->assertEquals($scripts[0]->path, $this->container->get('extension.list.module')->getPath('h5p_analytics') . '/js/external.js');
    $this->assertEquals($scripts[0]->version, '1.x');

    $scripts = [];

    h5p_analytics_h5p_scripts_alter($scripts, [], 'internal');
    $this->assertEquals($scripts, []);
  }

  /**
   * Test hook_cron().
   */
  public function testCron() {
    $lrsService = $this->prophesize(LrsServiceInterface::class);
    $lrsService->processStatementsCron()->shouldBeCalledTimes(1);
    $this->container->set('h5p_analytics.lrs', $lrsService->reveal());
    h5p_analytics_cron();
  }

}
