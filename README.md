Formulas question type for Moodle
---------------------------------
This is a question type plugin for Moodle with random values and multiple answer fields.

The answer fields can be placed anywhere in the question so that we can create questions involving various answer structures such as coordinate, polynomial and matrix.

Other features such as unit checking and multiple subquestions are also available.

These functionalities can simplify the creation of questions in many fields related to mathematics, numbers and units, such as physics and engineering. 

This question type was written by Hon Wai Lau and versions for Moodle 1.9 and 2.0 are still available at the original author's website at the date of this writting
https://code.google.com/p/moodle-coordinate-question/downloads/list

This question type was upgraded to the new question engine introduced in Moodle 2.1 by Jean-Michel Vedrine.

This version is compatible with Moodle 3.0 and ulterior versions. It has been tested with Moodle versions up to 3.9. It has also been tested with PHP versions 7.0, 7.1, 7.3 and 7.4.

If you are running an older version of Moodle another version of the formulas question type is available for Moodle versions 2.6 to 2.9.


### Requirements

You will need to install Tim Hunt's Adaptive question behaviour for multi-part questions (qbehaviour_adaptivemultipart) prior to installing the formulas question type.

You can get it from the Moodle plugin directory https://moodle.org/plugins/view.php?plugin=qbehaviour_adaptivemultipart
or from Github https://github.com/maths/moodle-qbehaviour_adaptivemultipart

You absolutely need version 3.3 or newer of this behaviour, the formulas question type will not work with previous versions.


### Installation

#### Installation from the Moodle plugin directory (prefered method)

This question type is available from https://moodle.org/plugins/view.php?plugin=qtype_formulas

Install as any other Moodle question type plugin

#### Installation Using Git

To install using git type these commands in the root of your Moodle install:
    git clone git://github.com/dbauer-ets/moodle-qtype_formulas.git question/type/formulas
    echo '/question/type/formulas/' >> .git/info/exclude


#### Installation From Downloaded zip file

Alternatively, download the zip from https://github.com/dbauer-ets/moodle-qtype_formulas

unzip it into the question/type folder, and then rename the new folder to formulas.

### Creating formulas questions
This question type is very powerful and permit creation of a wide range of questions.

But mastering all the possibilities require some practice and there is a learning curve on creating formulas questions.

Here are some pointers to the available help :
* first you can import the Moodle xml file samples/sample-formulas-questions.xml and play with the included formulas questions.
* You can visit the documentation made by Dominique Bauer https://moodleformulas.org/
  (As there is no or little difference in the Formulas question type plugin for recent versions of Moodle (2.0 and above),
  the documentation for the Formulas question type has been moved to this location but it apply to all Moodle versions,
  including the current release)
* you can read discussions about the formulas question type in the Moodle quiz forum
for instance https://moodle.org/mod/forum/discuss.php?d=181049 and https://moodle.org/mod/forum/discuss.php?d=163345
* you can post your questions in this forum

### Reporting bugs, problems

You can see the list of issues here: https://tracker.moodle.org/browse/CONTRIB-6381?jql=project%20%3D%20CONTRIB%20AND%20component%20%3D%20%22Question%20type%3A%20Formulas%22

To create a new tracker issue, Log in and click on "Create" in the menu bar. Choose "Plugins (CONTRIB" in the "Project" field and "Question type: formulas" in the "Component(s)" field. Try to include as much details as you can so that the problem can be reproduced.
