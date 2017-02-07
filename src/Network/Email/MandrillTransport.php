<?php
/**
 * Send mail using Mandrill (by MailChimp)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 *
 * @author        Lennaert van Dijke (http://lennaert.nu)
 * @link          http://lennaert.nu
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace MandrillEmail\Network\Email;

use Cake\Core\Configure;
use Cake\Network\Email\Email;
use Cake\Network\Exception\SocketException;
use Cake\Network\Http\Client;
use Cake\Utility\Hash;
use Cake\Network\Email\AbstractTransport;

/**
 * Send mail using mail() function
 *
 */
class MandrillTransport extends AbstractTransport
{
    public $http;

    public $key = null;

    public $defaultParameters = [
        'important'                 => false,
        'track_opens'               => null,
        'track_clicks'              => null,
        'auto_text'                 => null,
        'auto_html'                 => null,
        'inline_css'                => null,
        'url_strip_qs'              => null,
        'preserve_recipients'       => null,
        'view_content_link'         => null,
        'tracking_domain'           => null,
        'signing_domain'            => null,
        'return_path_domain'        => null,
        'merge'                     => true,
        'merge_language'            => 'mailchimp',
        'global_merge_vars'         => [ ],
        'tags'                      => [ ],
        'subaccount'                => null,
        'google_analytics_domains'  => [ ],
        'google_analytics_campaign' => null,
        'metadata'                  => [ ],

        // Non-message parameters.
        'send_at'                   => null,
        'template_name'             => null,
        'template_content'          => [ ],
    ];

    public $transportConfig = [
        'key'     => null,
        'async'   => true,
        'ip_pool' => null
    ];

/**
 * Send mail using Mandrill (by MailChimp)
 *
 * @param \Cake\Network\Email\Email $email Cake Email
 * @return array
 */
    public function send(Email $email)
    {
        $this->transportConfig = Hash::merge($this->transportConfig, $this->_config);

        // Initiate a new Mandrill Message parameter array
        $message = [
            'html'                      => $email->message(\Cake\Network\Email\Email::MESSAGE_HTML),
            'text'                      => $email->message(\Cake\Network\Email\Email::MESSAGE_TEXT),
            'subject'                   => $this->_decode($email->subject()), // Decode because Mandrill is encoding
            'from_email'                => key($email->from()), // Make sure the domain is registered and verified within Mandrill
            'from_name'                 => current($email->from()),
            'to'                        => [ ],
            'headers'                   => ['Reply-To' => is_null(key($email->replyTo()))?key($email->from()):key($email->replyTo())],
            'recipient_metadata'        => [ ],
            'attachments'               => [ ],
            'images'                    => [ ]
        ];

        // Merge Mandrill Parameters
        $message = array_merge($message, Hash::merge($this->defaultParameters, $email->profile()['Mandrill']));

        // Add receipients
        foreach (['to', 'cc', 'bcc'] as $type) {
            foreach ($email->{$type}() as $mail => $name) {
                $message['to'][] = [
                    'email' => $mail,
                    'name'  => $name,
                    'type'  => $type
                ];
            }
        }

        // Attachments
        $message = $this->_attachments($email, $message);

        // Create a new scoped Http Client
        $this->http = new Client([
            'host'   => 'mandrillapp.com',
            'scheme' => 'https',
            'headers' => [
                'User-Agent' => 'CakePHP Mandrill Plugin'
            ]
        ]);

        // Sending as a template? Then in case we find mail content, we add this as a 'template_content'.
        // In you Mandrill template, use <div mc:edit="content"></div> to get the contents of your email
        if (!is_null($message['template_name']) && $message['html']) {
            if (!isset($message['template_content']) || !is_array($message['template_content'])) {
                $message['template_content'] = [ ];
            }

            $message['template_content'][] = [
                'name'    => 'content',
                'content' => $message['html']
            ];
        }

        // Are we sending a template?
        if (!is_null($message['template_name']) && !empty($message['template_content'])) {
            return $this->_sendTemplate($message, $this->transportConfig['async'], $this->transportConfig['ip_pool'], $message['send_at']);
        } else {
            return $this->_send($message, $this->transportConfig['async'], $this->transportConfig['ip_pool'], $message['send_at']);
        }
    }


/**
 * Send normal email
 *
 * @param  array  $message The Message Array
 * @param  boolean $async  Send this request asyncronized?
 * @param  boolean $ipPool IP Pool if you have a dedicated IP
 * @return array Returns an array with the results from the Mandrill API
 */
    protected function _send($message, $async, $ipPool, $sendAt)
    {

        $payload = [
            'key'     => $this->transportConfig['key'],
            'message' => $message,
            'async'   => $async,
            'ip_pool' => $ipPool,
            'send_at' => $sendAt
        ];

        $response = $this->http->post('/api/1.0/messages/send.json', json_encode($payload), ['type' => 'json']);

        if (!$response || $response->code >= 300) {
            throw new SocketException($response->code);
        }

        return $response->json;
    }

/**
 * Send Template email
 * @param  array  $message The Message Array
 * @param  boolean $async  Send this request asyncronized?
 * @param  boolean $ipPool IP Pool if you have a dedicated IP
 * @return array Returns an array with the results from the Mandrill API
 */
    protected function _sendTemplate($message, $async, $ipPool, $sendAt)
    {

        $payload = [
            'key'              => $this->transportConfig['key'],
            'template_name'    => $message['template_name'],
            'template_content' => $message['template_content'],
            'message'          => $message,
            'async'            => $async,
            'ip_pool'          => $ipPool,
            'send_at'          => $sendAt
        ];

        $response = $this->http->post('/api/1.0/messages/send-template.json', json_encode($payload), ['type' => 'json']);

        if (!$response || $response->code >= 300) {
            throw new SocketException($response->code);
        }

        return $response->json;
    }

/**
 * Send a raw email
 *
 * @param  array  $message The Message Array
 * @param  boolean $async  Send this request asyncronized?
 * @param  boolean $ipPool IP Pool if you have a dedicated IP
 * @return array Returns an array with the results from the Mandrill API
 * @todo finish
 */
    protected function _sendRaw($message, $async, $ipPool, $sendAt)
    {

        $payload = [
            'key'     => $this->transportConfig['key'],
            'message' => $message,
            'async'   => $async,
            'ip_pool' => $ipPool,
            'send_at' => $sendAt
        ];

        $response = $this->http->post('/api/1.0/messages/send-raw.json', json_encode($payload), ['type' => 'json']);

        if (!$response) {
            throw new SocketException($response->code);
        }

        return $response->json;
    }


/**
 * Format the attachments
 *
 * @param Email $email
 * @param type $message
 * @return array Message
 */
    protected function _attachments(Email $email, $message = [])
    {
        foreach ($email->attachments() as $filename => $attach) {
            $content = base64_encode(file_get_contents($attach['file']));

            if (isset($attach['contentId'])) {
                $message['images'][] = [
                    'type'    => $attach['mimetype'],
                    'name'    => $attach['contentId'],
                    'content' => $content,
                ];
            } else {
                $message['attachments'][] = [
                    'type'    => $attach['mimetype'],
                    'name'    => $filename,
                    'content' => $content,
                ];
            }
        }

        return $message;
    }
    
/**
 * Decode the specified string using the current charset
 *
 * @param string $text String to decode
 * @return string Decoded string
 */
    protected function _decode($text) 
    {
        $restore = mb_internal_encoding();
        mb_internal_encoding(Configure::read('App.encoding'));
        $return = mb_decode_mimeheader($text);
        mb_internal_encoding($restore);
        return $return;
    }
}
