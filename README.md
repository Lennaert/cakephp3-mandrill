# CakePHP 3 Mandrill plugin

This is a Mandrill Email Transport plugin for CakePHP 3. To use, you'll need to have an active Mandrill account, set up with the email address you'll be sending from, and an API `key`.

To install this plugin, you're best off using composer. Add:

    "lennaert/cakephp3-mandrill": "*"

to your `composer.json` file and run.

## Setting up your CakePHP application ##

CakePHP uses [profiles and transports](http://book.cakephp.org/3.0/en/core-libraries/email.html#configuration) to set up your email configuration. In your configuration file (`app.php` or your own), add configuration arrays for the Mandrill profile and transport. The profile (first array) is the default settings for any Mandrill email, which keeps your email settings DRY. The second array is the Transport config, telling Cake where to find and how to use the Mandrill plugin. [There are many more options you can set in the profile array below](http://book.cakephp.org/3.0/en/core-libraries/email.html#configuration-profiles).

    'Email' => [
        'Mandrill' => [
            'template' => 'default',
            'layout' => 'default',
            'transport' => 'Mandrill',
            'emailFormat' => 'both',
            'from' => ['you@yours.com' => 'Bob Bobbington'],
            'sender' => ['you@yours.com' => 'Bob Bobbington'],
            'Mandrill' => [] // Don't ask, the plugin needs/wants this empty array
        ]
    ],
    'EmailTransport' => [
        'Mandrill' => [
            'className' => 'MandrillEmail\Network\Email\MandrillTransport',
            'host' => 'smtp.mandrillapp.com',
            'key' => '-----your-key-here-----'
        ]
    ]
    
## Sending some emails ##

Add the namespaces for emails and Mandrill:

    use MandrillEmail\Network\Email\MandrillTransport;
    use Cake\Network\Email\Email;
    
Then, create an Email, tell it which profile to use, tell it where to go and that's it!

    $emailObject
        ->subject('Mandrill sends emails')
        ->profile('Mandrill') // This is the profile you set above, in your config file
        ->to('me@mine.com', 'Mary Maristone')
        ->send();
        
If you're having trouble sending emails, make sure your `from` address is the email address set up in Mandrill.
