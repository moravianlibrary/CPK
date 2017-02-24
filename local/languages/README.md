Creating institution specific translations with GUI
---------------------------------------------------

This README describes what is needed to do in order to make the Translations & TranslationsApproval actions within an AdminController work.

If you are willing to use our Admin controller for editing institution-level translations, you should create a language file called ${LANG}-cpk-institutions.ini where LANG is a variable holding your language. So there will be as many  ${LANG}-cpk-institutions.ini files as you have languages in your VuFind.

These language files must be webserver writable, so you should place them in a institution-translations directory within the languages dir & symlink them to parent dir. You could do it e.g. this way

    mkdir institution-translations
    touch institution-translations/{cs,en}-cpk-institutions.ini
    ln -s institution-translations/cs-cpk-institutions.ini
    ln -s institution-translations/en-cpk-institutions.ini

Important thing to make it work is to change ownership of those institution language files to Apache's user (typically www-data)

    sudo chown www-data:www-data institution-translations/ -R


BELOW ARE DEPRECATED DOCS
------------------------

If you also like to enable the git version system for the translations in order to commit, stash, pull, rebase, push, stash apply the changes after each time an instituion-level translation is approved by portal admin, the php should have write privileges in the whole .git directory. But keep in mind that after each pull or push of a certain user, the git creates directories within a .git/objects directory with write permissions only for the user who ran the git pull or push. This refuses other users from pulling or pushing the changes.

We have solved this problem by creating another repository, where has write access only the www-data user. You can always write into the repository from another clone, which belongs only to your user. Pulling changes is then as easy, as creating an Apache Alias token, which points to a php script running this bash script :-D:

    #!/bin/sh
    if [ ! -f .locked ]; then
      touch .locked && git stash && git pull && git stash apply && rm .locked
      exit 0
    else
      exit 1
    fi

There may also be a chance file .locked already exists, you should then abort any git command & try again later.

Finally, just create symlinks in this directory to those language files stored in another repository & set it up in config.ini

You can find another use of this second-repo coding style by storing there your private institution configs, which may be edited by Configurations & ConfigurationsApproval actions within an AdminController. Again, the user www-data should only have permissions over the whole repository clone.
