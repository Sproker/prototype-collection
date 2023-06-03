<?php

namespace Drupal\Tests\h5p_analytics\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the JavaScript functionality of the H5P Analytics module.
 *
 * @group h5p_analytics
 */
class ConfigurationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['h5p_analytics'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $adminUser = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($adminUser);
  }

  /**
   * Tests LRS connection test button.
   */
  public function testConnectionCallback() {
    $this->config('h5p_analytics.settings')
      ->set('xapi_endpoint', 'https://endpoint')
      ->set('key', 'key')
      ->set('secret', 'secret')
      ->save();

    $this->drupalGet('admin/config/system/h5p_analytics');

    $page = $this->getSession()->getPage();
    $page->findButton('Test LRS connection')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Service responded with code 0 and message .');
    $this->assertSession()->pageTextContains('cURL error 6: Could not resolve host:');
  }

}
