.. include:: ../../Includes.txt

Installation
============

Installation using composer
---------------------------

The recommended way to install the extension is by using `Composer <https://getcomposer.org/>`_.
In your composer based TYPO3 project root, just do

`composer require extcode/cart-pdf`.

Installation from TYPO3 Extension Repository (TER)
--------------------------------------------------

Download and install the extension with the extension manager module.

Latest version from git
-----------------------
You can get the latest version from git by using the git command:

.. code-block:: bash

   git clone git@github.com:extcode/cart_pdf.git

Preparation: Include static TypoScript
--------------------------------------

The extension ships some TypoScript code which needs to be included.

#. Switch to the root page of your site.

#. Switch to the **Template module** and select *Info/Modify*.

#. Press the link **Edit the whole template record** and switch to the tab *Includes*.

#. Select **Shopping Cart - Cart Pdf** at the field *Include static (from extensions):*

#. Select one or more of **Shopping Cart - Cart Pdf - Order PDF**, **Shopping Cart - Cart Pdf - Invoice PDF**, **Shopping Cart - Cart Pdf - Delivery PDF** at the field *Include static (from extensions):*
