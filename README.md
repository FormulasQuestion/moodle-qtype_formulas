Formulas question type for Moodle
---------------------------------
This is a question type plugin for Moodle with random values and multiple answer fields.

The answer fields can be placed anywhere in the question so that we can create questions involving various answer structures such as coordinate, polynomial and matrix.

Other features such as unit checking and multiple subquestions are also available.

These functionalities can simplify the creation of questions in many fields related to mathematics, numbers and units, such as physics and engineering. 

This question type was written by Hon Wai Lau and versions for Moodle 1.9 and 2.0 are still available at the original author website at the date of this writting
https://code.google.com/p/moodle-coordinate-question/downloads/list

This question type was upgraded to the new question engine introduced in Moodle 2.1 by Jean-Michel Vedrine

This version is compatible with Moodle 2.6 and ulterior versions.


###Requirements

You will need to install Tim Hunt's Adaptive question behaviour for multi-part questions (qbehaviour_adaptivemultipart) prior to installing the formulas question type.

You can get it from the Moodle plugin directory https://moodle.org/plugins/view.php?plugin=qbehaviour_adaptivemultipart
or from Github https://github.com/maths/moodle-qbehaviour_adaptivemultipart

You absolutely need version 3.3 or newer of this behaviour, the formulas question type will not work with previous versions.


###Installation

####Installation from the Moodle plugin directory
This question type is available from https://moodle.org/plugins/view.php?plugin=qtype_formulas

Install as other Moodle question type plugin

####Installation Using Git 

To install using git type these commands in the root of your Moodle install:
    git clone git://github.com/jmvedrine/moodle-qtype_formulas.git question/type/formulas
    echo '/question/type/formulas/' >> .git/info/exclude


####Installation From Downloaded zip file

Alternatively, download the zip from https://github.com/jmvedrine/moodle-qtype_formulas

unzip it into the question/type folder, and then rename the new folder to formulas.

### Creating formulas questions ###
This question type is very powerful and permit creation of a wide range of questions.

But mastering all the possibilities require some practice and there is a learning curve on creating formulas questions.

Unfortunately there is not yet an up-to date documentation, so here are some pointers to the available help :
* first you can import the Moodle xml file samples/sample-formulas-questions.xml and play with the included formulas questions.
* You can visit Hon Wai Lau's original tutorial and documentation for the version for Moodle 1.9 at 
https://code.google.com/p/moodle-coordinate-question/wiki/Tutorial
and
https://code.google.com/p/moodle-coordinate-question/wiki/Documentation
but be warned there are some differences with the actual version
* you can read discussions about the formulas question type in the Moodle quiz forum
for instance https://moodle.org/mod/forum/discuss.php?d=181049 and https://moodle.org/mod/forum/discuss.php?d=163345
* you can post your questions in this forum