Formulas question type for Moodle
---------------------------------

[![Automated code checks](https://github.com/FormulasQuestion/moodle-qtype_formulas/actions/workflows/checks.yml/badge.svg)](https://github.com/FormulasQuestion/moodle-qtype_formulas/actions/workflows/checks.yml) [![Automated acceptance tests](https://github.com/FormulasQuestion/moodle-qtype_formulas/actions/workflows/behat.yml/badge.svg)](https://github.com/FormulasQuestion/moodle-qtype_formulas/actions/workflows/behat.yml) [![Automated unit tests](https://github.com/FormulasQuestion/moodle-qtype_formulas/actions/workflows/testing.yml/badge.svg)](https://github.com/FormulasQuestion/moodle-qtype_formulas/actions/workflows/testing.yml) [![Coverage Status](https://coveralls.io/repos/github/FormulasQuestion/moodle-qtype_formulas/badge.svg)](https://coveralls.io/github/FormulasQuestion/moodle-qtype_formulas) [![GitHub
Release](https://img.shields.io/github/release/FormulasQuestion/moodle-qtype_formulas.svg)](https://github.com/FormulasQuestion/moodle-qtype_formulas/releases)

This is a question type plugin for Moodle with random values and multiple answer fields.
The answer fields can be placed anywhere in the question so that we can create questions
involving various answer structures such as coordinate, polynomial and matrix. Other
features such as unit checking and multiple subquestions are also available.

These functionalities can simplify the creation of questions in many fields related to
mathematics, numbers and units, such as physics and engineering.

This question type was written by Hon Wai Lau and versions for Moodle 1.9 and 2.0 are
still available at the [original author's website](https://code.google.com/p/moodle-coordinate-question/downloads/list)
at the date of this writing. It was then upgraded to the new question engine introduced in Moodle 2.1 by
Jean-Michel Védrine.

This version is compatible with Moodle 3.9 and newer. It has been tested with:
- Moodle 3.9 using PHP 7.4
- Moodle 3.11 using PHP 7.4 and PHP 8.0
- Moodle 4.0 using PHP 7.4 and PHP 8.0
- Moodle 4.1 using PHP 7.4, PHP 8.0 and PHP 8.1
- Moodle 4.2 using PHP 8.0, PHP 8.1 and PHP 8.2
- Moodle 4.3 using PHP 8.0, PHP 8.1 and PHP 8.2
- Moodle 4.4 using PHP 8.1, PHP 8.2 and PHP 8.3


### Requirements

You will need to install Tim Hunt's
[Adaptive question behaviour for multi-part questions (qbehaviour_adaptivemultipart)](https://moodle.org/plugins/view.php?plugin=qbehaviour_adaptivemultipart)
prior to installing the formulas question type. You can also
[get it from GitHub](https://github.com/maths/moodle-qbehaviour_adaptivemultipart).

You absolutely need version 3.3 or newer of this behaviour, the formulas question type will not work with previous versions.


### Installation

#### Installation from the Moodle plugin directory (prefered method)

1. Download the plugin from the [Moodle plugin directory](https://moodle.org/plugins/view.php?plugin=qtype_formulas).
2. Install as any other Moodle question type plugin.

#### Installation Using Git

To install using git type these commands in the root directory of your Moodle install:

```bash
$ git clone git://github.com/FormulasQuestion/moodle-qtype_formulas.git question/type/formulas
$ echo '/question/type/formulas/' >> .git/info/exclude
```

#### Installation From Downloaded ZIP file

Alternatively, [download the zip](https://github.com/FormulasQuestion/moodle-qtype_formulas) and
unzip it into the `$MOODLE_ROOT/question/type` folder. Do not forget to rename the new
folder to `formulas`.

### Creating formulas questions

This question type is very powerful and permit creation of a wide range of questions.
But mastering all the possibilities requires some practice and there is a learning curve
on creating formulas questions.

Here are some pointers to the available help :
* First, you can import the Moodle XML file `samples/sample-formulas-questions.xml`
  and play with the included formulas questions.
* You can visit [the documentation](https://dynamiccourseware.org/) made by Dominique Bauer.
  As there is no or little difference in the Formulas question type plugin for recent
  versions of Moodle (2.0 and above), the documentation for the Formulas question type has
  been moved to this location but it applies to all Moodle versions, including the current release.
* You can read discussions about the formulas question type in the
  [Moodle quiz forum](https://moodle.org/mod/forum/view.php?id=737)
  like, for example,
  [the thread where Jean-Michel Védrine presents the (then) new version for Moodle 2.0](https://moodle.org/mod/forum/discuss.php?d=181049)
  or [this one from Hon Wai Lau](https://moodle.org/mod/forum/discuss.php?d=163345)
* You can post your own questions in this forum.

### Reporting bugs, problems

Please [open an issue on GitHub](https://github.com/FormulasQuestion/moodle-qtype_formulas/issues/new).

You can also open an issue in the
[Moodle Tracker](https://tracker.moodle.org/browse/CONTRIB-8735?jql=project%20%3D%20CONTRIB%20AND%20component%20%3D%20%22Question%20type%3A%20Formulas%22)

To create a new tracker issue:
1. Log in and click on the *Create* button in the menu bar.
2. Choose *Plugins (CONTRIB)* in the "Project" field.
3. Set the "Component(s)" field to *Question type: Formulas*.
4. Try to include as many details as you can so that the problem can be reproduced.
