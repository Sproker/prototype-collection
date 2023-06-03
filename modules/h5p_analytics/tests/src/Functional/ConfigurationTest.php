<?php

namespace Drupal\Tests\h5p_analytics\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test description.
 *
 * @group h5p_analytics
 */
class ConfigurationTest extends BrowserTestBase {

  const CONFIG_PATH = 'admin/config/system/h5p_analytics';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['h5p_analytics'];

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer site configuration']);
  }

  /**
   * Tests config page access with anonymous and admin user. Makes sure that all fields exist and have correct values.
   */
  public function testConfigurationForm() {
    $this->drupalGet(self::CONFIG_PATH);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet(self::CONFIG_PATH);
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->fieldExists('xapi_endpoint');
    $this->assertSession()->fieldValueEquals('xapi_endpoint', '');

    $this->assertSession()->fieldExists('key');
    $this->assertSession()->fieldValueEquals('key', '');

    $this->assertSession()->fieldExists('secret');
    $this->assertSession()->fieldValueEquals('secret', '');

    $this->assertSession()->fieldExists('batch_size');
    $this->assertSession()->fieldValueEquals('batch_size', 100);

    $this->assertSession()->fieldExists('timeout');
    $this->assertSession()->fieldValueEquals('timeout', 45.0);
  }

  /**
   * Tests configuration page and makes sure it can be saved.
   */
  public function testConfigurationFormSubmit() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet(self::CONFIG_PATH);
    $page = $this->getSession()->getPage();
    $page->fillField('xapi_endpoint', 'https://endpoint');
    $page->fillField('key', 'key');
    $page->fillField('secret', 'secret');
    $page->fillField('batch_size', 150);
    $page->fillField('timeout', 50.0);
    $page->findButton('Save configuration')->click();
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $this->drupalGet(self::CONFIG_PATH);
    $this->assertSession()->fieldValueEquals('xapi_endpoint', 'https://endpoint');
    $this->assertSession()->fieldValueEquals('key', 'key');
    $this->assertSession()->fieldValueEquals('secret', 'secret');
    $this->assertSession()->fieldValueEquals('batch_size', 150);
    $this->assertSession()->fieldValueEquals('timeout', 50.0);
  }

}
