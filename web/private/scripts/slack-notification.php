<?php
/**
 * Quicksilver Script: Slack Notification
 * Description: Send a notification to a Slack channel when code is deployed to Pantheon.
 */

$pantheon_yellow = '#FFDC28';
$slack_channel = '#firehose';

/**
 * Build an array of fields to be rendered with Slack Attachments as a table using markdown and block-based sections.
 */
$base_blocks = [
    [
        'type' => 'section',
        'fields' => [
            [
                'type' => 'mrkdwn',
                'text' => "*Site:*\n" . $_ENV['PANTHEON_SITE_NAME'],
            ],
            [
                'type' => 'mrkdwn',
                'text' => "*Environment:*\n<http://" . $_ENV['PANTHEON_ENVIRONMENT'] . "-" . $_ENV['PANTHEON_SITE_NAME'] . ".pantheonsite.io|" . $_ENV['PANTHEON_ENVIRONMENT'] . ">",
            ],
            [
                'type' => 'mrkdwn',
                'text' => "*By:*\n" . $_POST['user_email'],
            ],
            [
                'type' => 'mrkdwn',
                'text' => "*Workflow:*\n" . ucfirst($_POST['stage']) . " " . str_replace('_', ' ', $_POST['wf_type']),
            ],
        ],
    ],
];

$blocks = $base_blocks;
printf($_POST['wf_type']);
/**
 * Customize the message based on the workflow type.  Note that slack_notification.php must appear in your pantheon.yml for each workflow type you wish to send notifications on.
 */
switch ($_POST['wf_type']) {
    case 'deploy':
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*Deploying* :rocket:",
            ],
        ];
        $deploy_message = $_POST['deploy_message'];
        $blocks[] = [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "*Details:*\nDeploy to the " . $_ENV['PANTHEON_ENVIRONMENT'] . " environment of " . $_ENV['PANTHEON_SITE_NAME'] . " by " . $_POST['user_email'] . " complete!",
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*Deploy Note:*\n" . $deploy_message,
                ],
            ],
        ];
        break;

    case 'sync_code':
    case 'sync_code_external_vcs':
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*Syncing Code* :computer:",
            ],
        ];
        $committer = trim(`git log -1 --pretty=%cn`);
        $hash = trim(`git log -1 --pretty=%h`);
        $message = trim(`git log -1 --pretty=%B`);
        $blocks[] = [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "*Commit:*\n$hash",
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*Commit Message:*\n$message",
                ],
            ],
        ];
        break;

    case 'clear_cache':
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*Clearing Cache* :broom:",
            ],
        ];
        $blocks[] = [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "*Action:*\nCaches cleared on <http://" . $_ENV['PANTHEON_ENVIRONMENT'] . "-" . $_ENV['PANTHEON_SITE_NAME'] . ".pantheonsite.io|" . $_ENV['PANTHEON_ENVIRONMENT'] . ">.",
                ],
            ],
        ];
        break;

    default:
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*Workflow Notification* :bell:",
            ],
        ];
        $description = $_POST['qs_description'] ?? 'No additional details provided.';
        $blocks[] = [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "*Details:*\n" . $description,
                ],
            ],
        ];
        break;
}

$attachments = [
	[
		'color' => $pantheon_yellow,
		'blocks' => $blocks,
	],
];

_slack_notification( $slack_channel, $attachments );

/**
 * Send a notification to slack
 * 
 * @param string $channel The channel to send the notification to.
 * @param string $blocks The message to send.
 */
function _slack_notification($channel, $attachments) {
    $slack_token = pantheon_get_secret('slack_deploybot_token');
    $post = [
        'channel' => $channel,
        'attachments' => $attachments,
    ];

    $payload = json_encode($post);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://slack.com/api/chat.postMessage');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $slack_token,
        'Content-Type: application/json; charset=utf-8',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $result = curl_exec($ch);
    $response = json_decode($result, true);

    print("\n==== Posting to Slack ====\n");
    print("RESULT: " . print_r($response, true));

    if (!$response['ok']) {
        print("Error: " . $response['error'] . "\n");
        error_log("Slack API error: " . $response['error']);
        return;
    }

    print("Message sent successfully!\n");
    curl_close($ch);
}
