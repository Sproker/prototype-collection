<?php

namespace Drupal\Tests\h5p_analytics\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test description.
 *
 * @group h5p_analytics
 */
class LrsControllerTest extends BrowserTestBase {

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
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer site configuration']);
  }

  /**
   * Tests statistics page with anonymous and privileged users.
   */
  public function testStatisticsPage() {
    $this->drupalGet('admin/reports/h5p_analytics/statistics');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/reports/h5p_analytics/statistics');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('xAPI LRS statement statistics');
    $this->assertSession()->pageTextContains('H5P analytics LRS statistics');
  }

  /**
   * Tests request log page with anonymous and privileged users.
   */
  public function testRequestLogPage() {
    $this->drupalGet('admin/reports/h5p_analytics/requests');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/reports/h5p_analytics/requests');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('xAPI LRS request log');
    $this->assertSession()->pageTextContains('No requests found in the log');
  }

}
