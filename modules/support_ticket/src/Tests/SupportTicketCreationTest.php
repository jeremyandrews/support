<?php

/**
 * @file
 * Contains \Drupal\support_ticket\Tests\SupportTicketCreationTest.
 */

namespace Drupal\support_ticket\Tests;

use Drupal\Core\Database\Database;
use Drupal\Core\Language\LanguageInterface;

/**
 * Create a support ticket and test saving it.
 *
 * @group support_ticket
 */
class SupportTicketCreationTest extends SupportTicketTestBase {

  /**
   * Modules to enable.
   *
   * Enable dummy module that implements hook_ENTITY_TYPE_insert() for
   * exceptions (function support_ticket_test_exception_support_ticket_insert() ).
   *
   * @var array
   */
  public static $modules = array('support_ticket_test_exception', 'dblog', 'test_page_test');

  protected function setUp() {
    parent::setUp();

    $web_user = $this->drupalCreateUser(array('access support tickets', 'create ticket ticket', 'edit own ticket ticket'));
    $this->drupalLogin($web_user);
  }

  /**
   * Creates a "ticket" support_ticket and verifies its consistency in the database.
   */
  function testTicketCreation() {
    $ticket_type_storage = \Drupal::entityManager()->getStorage('support_ticket_type');

    // Test /support_ticket/add page with only one content type.
//    $ticket_type_storage->load('article')->delete();
    $this->drupalGet('support_ticket/add');
    $this->assertResponse(200);
    $this->assertUrl('support_ticket/add/ticket');
    // Create a ticket.
    $edit = array();
    $edit['title[0][value]'] = $this->randomMachineName(8);
    $edit['body[0][value]'] = $this->randomMachineName(16);
    //$edit['field_priority[value]'] = 'high';
    $this->drupalPostForm('support_ticket/add/ticket', $edit, t('Save'));

    // Check that the Basic page has been created.
    $this->assertRaw(t('@post %title has been created.', array('@post' => 'Ticket', '%title' => $edit['title[0][value]'])), 'Ticket created.');

    // Check that the support_ticket exists in the database.
    $ticket = $this->supportTicketGetTicketByTitle($edit['title[0][value]']);
    $this->assertTrue($ticket, 'Ticket found in database.');

    // Verify that pages do not show submitted information by default.
    // @todo Should the opposite be true for support tickets?
    $this->drupalGet('support_ticket/' . $ticket->id());
//    $this->assertNoText($ticket->getOwner()->getUsername());
//    $this->assertNoText(format_date($ticket->getCreatedTime()));

    // Change the support_ticket type setting to show submitted by information.
    /** @var \Drupal\support_ticket\SupportTicketTypeInterface $ticket_type */
    $ticket_type = $ticket_type_storage->load('ticket');
    $ticket_type->setDisplaySubmitted(TRUE);
    $ticket_type->save();

    $this->drupalGet('support_ticket/' . $ticket->id());
    $this->assertText($ticket->getOwner()->getUsername());
    $this->assertText(format_date($ticket->getCreatedTime()));
  }

  /**
   * Verifies that a transaction rolls back the failed creation.
   */
  function testFailedTicketCreation() {
    // Create a support_ticket.
    $edit = array(
      'uid'      => $this->loggedInUser->id(),
      'name'     => $this->loggedInUser->name,
      'support_ticket_type'     => 'ticket',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'title'    => 'testing_transaction_exception',
    );

    try {
      // An exception is generated by support_ticket_test_exception_support_ticket_insert() if the
      // title is 'testing_transaction_exception'.
      entity_create('support_ticket', $edit)->save();
      $this->fail(t('Expected exception has not been thrown.'));
    }
    catch (\Exception $e) {
      $this->pass(t('Expected exception has been thrown.'));
    }

    if (Database::getConnection()->supportsTransactions()) {
      // Check that the support_ticket does not exist in the database.
      $support_ticket = $this->supportTicketGetTicketByTitle($edit['title']);
      $this->assertFalse($support_ticket, 'Transactions supported, and support_ticket not found in database.');
    }
    else {
      // Check that the support_ticket exists in the database.
      $support_ticket = $this->supportTicketGetTicketByTitle($edit['title']);
      $this->assertTrue($support_ticket, 'Transactions not supported, and support_ticket found in database.');

      // Check that the failed rollback was logged.
      $records = static::getWatchdogIdsForFailedExplicitRollback();
      $this->assertTrue(count($records) > 0, 'Transactions not supported, and rollback error logged to watchdog.');
    }

    // Check that the rollback error was logged.
    $records = static::getWatchdogIdsForTestExceptionRollback();
    $this->assertTrue(count($records) > 0, 'Rollback explanatory error logged to watchdog.');
  }

  /**
   * Creates an unpublished support_ticket and confirms correct redirect behavior.
   */
  /*
  function testUnpublishedSupportTicketCreation() {
    // Set the front page to the test page.
    $this->config('system.site')->set('page.front', '/test-page')->save();

    // Set "Basic page" content type to be unpublished by default.
    $fields = \Drupal::entityManager()->getFieldDefinitions('support_ticket', 'page');
    $fields['status']->getConfig('page')
      ->setDefaultValue(FALSE)
      ->save();

    // Create a support_ticket.
    $edit = array();
    $edit['title[0][value]'] = $this->randomMachineName(8);
    $edit['body[0][value]'] = $this->randomMachineName(16);
    $this->drupalPostForm('support_ticket/add/page', $edit, t('Save'));

    // Check that the user was redirected to the home page.
    $this->assertUrl('');
    $this->assertText(t('Test page text'));

    // Confirm that the support_ticket was created.
    $this->assertRaw(t('@post %title has been created.', array('@post' => 'Basic page', '%title' => $edit['title[0][value]'])));
  }
  */

  /**
   * Tests the author autocompletion textfield.
   */
  /*
  public function testAuthorAutocomplete() {
    $admin_user = $this->drupalCreateUser(array('administer support_tickets', 'create page content'));
    $this->drupalLogin($admin_user);

    $this->drupalGet('support_ticket/add/page');

    $result = $this->xpath('//input[@id="edit-uid-0-value" and contains(@data-autocomplete-path, "user/autocomplete")]');
    $this->assertEqual(count($result), 0, 'No autocompletion without access user profiles.');

    $admin_user = $this->drupalCreateUser(array('administer support_tickets', 'create page content', 'access user profiles'));
    $this->drupalLogin($admin_user);

    $this->drupalGet('support_ticket/add/page');

    $result = $this->xpath('//input[@id="edit-uid-0-target-id" and contains(@data-autocomplete-path, "/entity_reference_autocomplete/user/default")]');
    $this->assertEqual(count($result), 1, 'Ensure that the user does have access to the autocompletion');
  }
  */

  /**
   * Check support_ticket/add when no support_ticket types exist.
   */
  /*
  function testSupportTicketAddWithoutContentTypes () {
    $this->drupalGet('support_ticket/add');
    $this->assertResponse(200);
    $this->assertNoLinkByHref('/admin/structure/types/add');

    // Test /support_ticket/add page without content types.
    foreach (\Drupal::entityManager()->getStorage('support_ticket_type')->loadMultiple() as $entity ) {
      $entity->delete();
    }

    $this->drupalGet('support_ticket/add');
    $this->assertResponse(403);

    $admin_content_types = $this->drupalCreateUser(array('administer content types'));
    $this->drupalLogin($admin_content_types);

    $this->drupalGet('support_ticket/add');

    $this->assertLinkByHref('/admin/structure/types/add');
  }
  */

  /**
   * Gets the watchdog IDs of the records with the rollback exception message.
   *
   * @return int[]
   *   Array containing the IDs of the log records with the rollback exception
   *   message.
   */
  protected static function getWatchdogIdsForTestExceptionRollback() {
    // PostgreSQL doesn't support bytea LIKE queries, so we need to unserialize
    // first to check for the rollback exception message.
    $matches = array();
    $query = db_query("SELECT wid, variables FROM {watchdog}");
    foreach ($query as $row) {
      $variables = (array) unserialize($row->variables);
      if (isset($variables['@message']) && $variables['@message'] === 'Test exception for rollback.') {
        $matches[] = $row->wid;
      }
    }
    return $matches;
  }

  /**
   * Gets the log records with the explicit rollback failed exception message.
   *
   * @return \Drupal\Core\Database\StatementInterface
   *   A prepared statement object (already executed), which contains the log
   *   records with the explicit rollback failed exception message.
   */
  protected static function getWatchdogIdsForFailedExplicitRollback() {
    return db_query("SELECT wid FROM {watchdog} WHERE message LIKE 'Explicit rollback failed%'")->fetchAll();
  }

}
