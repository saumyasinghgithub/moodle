@mod @mod_svasu @_file_upload @_switch_iframe
Feature: Add svasu activity
  In order to let students access a svasu package
  As a teacher
  I need to add svasu activity to a course

  @javascript
  Scenario: Add a svasu activity to a course
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "SVASU package" to section "1"
    And I set the following fields to these values:
      | Name | Awesome SVASU package |
      | Description | Description |
    And I upload "mod/svasu/tests/packages/singlesco_svasu12.zip" file to "Package file" filemanager
    And I click on "Save and display" "button"
    Then I should see "Awesome SVASU package"
    And I should see "Normal"
    And I should see "Preview"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Awesome SVASU package"
    And I should see "Normal"
    And I press "Enter"
    And I switch to "svasu_object" iframe
    And I should see "Not implemented yet"
    And I switch to the main frame
    And I am on "Course 1" course homepage
