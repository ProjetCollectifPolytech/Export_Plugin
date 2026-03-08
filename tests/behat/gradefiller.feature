@local_gradefiller @javascript
Feature: Grade filler upload and processing
  In order to fill grade spreadsheets with Moodle grades
  As a teacher
  I need to be able to upload a spreadsheet and download it filled with grades

  Background:
    Given the following "courses" exist:
      | fullname         | shortname | format |
      | Test Course      | TC101     | topics |
    And the following "users" exist:
      | username | firstname | lastname | email                  | idnumber |
      | teacher1 | Alice     | Teacher  | teacher1@example.com   | T-001    |
      | student1 | Bob       | Student  | student1@example.com   | S-001    |
      | student2 | Carol     | Student  | student2@example.com   | S-002    |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC101  | editingteacher |
      | student1 | TC101  | student        |
      | student2 | TC101  | student        |
    And the following "activities" exist:
      | activity | name        | course | idnumber |
      | assign   | Assignment1 | TC101  | A1       |

  # --------------------------------------------------------------------------
  # Navigation access
  # --------------------------------------------------------------------------

  Scenario: Teacher can access the grade filler from the activity settings
    Given I log in as "teacher1"
    When I am on the "Assignment1" "assign" activity page
    Then I should see "Fill grades in a spreadsheet" in the "Administration" "block"

  Scenario: Student does not see the grade filler link
    Given I log in as "student1"
    When I am on the "Assignment1" "assign" activity page
    Then I should not see "Fill grades in a spreadsheet" in the "Administration" "block"

  # --------------------------------------------------------------------------
  # Upload form
  # --------------------------------------------------------------------------

  @local_gradefiller_upload
  Scenario: Teacher sees the spreadsheet upload form
    Given I log in as "teacher1"
    When I am on the grade filler page for "Assignment1" in course "TC101"
    Then I should see "Fill Grades in a Spreadsheet"
    And I should see "Spreadsheet file"
    And I should see "Spreadsheet format"
    And I should see "Grade source"
    And I should see "Process and Download Filled File"

  # --------------------------------------------------------------------------
  # Grade source options
  # --------------------------------------------------------------------------

  @local_gradefiller_upload
  Scenario: Teacher can see the grade source options
    Given I log in as "teacher1"
    When I am on the grade filler page for "Assignment1" in course "TC101"
    Then I should see "Standard (Moodle User ID Number only)"
    And I should see "Anonymous (Activity-specific codes only)"

  # --------------------------------------------------------------------------
  # Upload without file
  # --------------------------------------------------------------------------

  @local_gradefiller_upload
  Scenario: Submitting without a file shows an error
    Given I log in as "teacher1"
    When I am on the grade filler page for "Assignment1" in course "TC101"
    And I press "Process and Download Filled File"
    Then I should see "No file uploaded"

  # --------------------------------------------------------------------------
  # Anonymous support indicator
  # --------------------------------------------------------------------------

  @local_gradefiller_upload
  Scenario: The anonymous support badge is shown only for compatible activities
    Given I log in as "teacher1"
    When I am on the grade filler page for "Assignment1" in course "TC101"
    # For a plain assign, no anonymous driver matches, so the badge is absent.
    Then I should not see "Anonymous grades supported"
