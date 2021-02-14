# Fast Waitlist

If you run events in CiviCRM with waitlists, the process of a participant coming off the waitlist involves them
receiving an email with a link.  Once they click the link, they must click a "Confirm Registration" button,
then they must repeat the registration process.  Some, but not all, of their existing registration data is 
pre-filled.

For paid events, it makes sense to bring participants to the registration screen - but for free events, it
makes more sense for them to simply click "Confirm Registration".  This extension changes the registration
confirmation screen so that's all participants need to do.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.0+
* CiviCRM 5.20+ (but should work on any CiviCRM that has API4 and PHP7.0+).

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl fastwaitlist@https://lab.civicrm.org/extensions/fastwaitlist/-/archive/master/fastwaitlist-master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/FIXME/fastwaitlist.git
cv en fastwaitlist
```

## Usage

There's no usage instructions - once this is installed, the "Confirm Your Registration" screen skips unnecessary steps.

## Potential Improvements

Fast Waitlist reloads the confirmation screen with a new status message telling people they're registered.  Support for
the [Front End Page Options](https://civicrm.org/extensions/front-end-page-options) extension to redirect users to a
different thank-you page could be incorporated for a better user experience.
