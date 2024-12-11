<?php
/**
 * Quicksilver Script: Slack Notification
 * Description: Send a notification to a Slack channel when code is deployed to Pantheon.
 */

// "Important" constants.
$pantheon_yellow = '#FFDC28';

/** 
 * Default values for parameters - this will assume the channel you define the webhook for.
 * The full Slack Message API allows you to specify other channels and enhance the messagge further if you like: https://api.slack.com/docs/messages/builder.
 */
$defaults = [
	'always_show_text' => false,
	'slack_channel' => '#firehose',
];

/**
 * Build an array of fields to be rendered with Slack Attachments as a table attachment-style formatting: https://api.slack.com/docs/attachments
 */
$fields = [
	[
		'title' => 'Site',
		'value' => $_ENV['PANTHEON_SITE_NAME'],
		'short' => 'true',
	],
	[ // Render Environment name with link to site, <http://{ENV}-{SITENAME}.pantheon.io|{ENV}>.
		'title' => 'Environment',
		'value' => '<http://' . $_ENV['PANTHEON_ENVIRONMENT'] . '-' . $_ENV['PANTHEON_SITE_NAME'] . '.pantheonsite.io|' . $_ENV['PANTHEON_ENVIRONMENT'] . '>',
		'short' => 'true',
	],
	[ // Render Name with link to Email from Commit message.
		'title' => 'By',
		'value' => $_POST['user_email'],
		'short' => 'true',
	],
	[ // Render workflow phase that the message was sent.
		'title' => 'Workflow',
		'value' => ucfirst( $_POST['stage'] ) . ' ' . str_replace( '_', ' ', $_POST['wf_type'] ),
		'short' => 'true',
	],
	[
		'title' => 'View Dashboard',
		'value' => '<https://dashboard.pantheon.io/sites/' . PANTHEON_SITE . '#' . PANTHEON_ENVIRONMENT . '/deploys|View Dashboard>',
		'short' => 'true',
	],
];

/**
 * Customize the message based on the workflow type.  Note that slack_notification.php must appear in your pantheon.yml for each workflow type you wish to send notifications on.
 */
print("\n==== Debugging Workflow Type ====\n");
print("wf_type: " . $_POST['wf_type'] . "\n");
switch ( $_POST['wf_type'] ) {
	case 'deploy':
		// Find out what tag we are on and get the annotation.
		$deploy_tag = `git describe --tags`;
		$deploy_message = $_POST['deploy_message'];

		// Prepare the slack payload as per: https://api.slack.com/incoming-webhooks.
		$text = 'Deploy to the ' . $_ENV['PANTHEON_ENVIRONMENT'];
		$text .= ' environment of ' . $_ENV['PANTHEON_SITE_NAME'] . ' by ' . $_POST['user_email'] . ' complete!';
		$text .= ' <https://dashboard.pantheon.io/sites/' . PANTHEON_SITE . '#' . PANTHEON_ENVIRONMENT . '/deploys|View Dashboard>';
		// Build an array of fields to be rendered with Slack Attachments as a table attachment-style formatting: https://api.slack.com/docs/attachments.
		$fields[] = [
			'title' => 'Details',
			'value' => $text,
			'short' => 'false',
		];
		$fields[] = [
			'title' => 'Deploy Note',
			'value' => $deploy_message,
			'short' => 'false',
		];  
		break;

	case 'sync_code':
		$git_version = trim(`git --version`);
		print("\n==== Debugging Git Availability ====\n");
		print("Git Version: $git_version\n");

		// Get the committer, hash, and message for the most recent commit.
		$committer = `git log -1 --pretty=%cn`;
		$email = `git log -1 --pretty=%ce`;
		$message = `git log -1 --pretty=%B`;
		$hash = `git log -1 --pretty=%h`;		
		print("\n==== Debugging git log ====\n");
		print("Committer: $committer\n");
		print("Hash: $hash\n");
		print("Message: $message\n");
		

		// Prepare the slack payload as per: https://api.slack.com/incoming-webhooks.
		$text = 'Code sync to the ' . $_ENV['PANTHEON_ENVIRONMENT'] . ' environment of ' . $_ENV['PANTHEON_SITE_NAME'] . ' by ' . $_POST['user_email'] . "!\n";
		$text .= 'Most recent commit: ' . rtrim( $hash ) . ' by ' . rtrim( $committer ) . ': ' . $message;
		// Build an array of fields to be rendered with Slack Attachments as a table attachment-style formatting: https://api.slack.com/docs/attachments.
		$fields = array_merge($fields, [
			[
				'title' => 'Commit',
				'value' => rtrim( $hash ),
				'short' => 'true',
			],
			[
				'title' => 'Commit Message',
				'value' => rtrim( $message ),
				'short' => 'false',
			],
		]);
		break;

	case 'clear_cache':
		$fields[] = [
			'title' => 'Cleared caches',
			'value' => 'Cleared caches on the ' . $_ENV['PANTHEON_ENVIRONMENT'] . ' environment of ' . $_ENV['PANTHEON_SITE_NAME'] . "!\n",
			'short' => 'false',
		];
		break;

	default:
		$text = $_POST['qs_description'];
		break;
}

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

switch ($_POST['wf_type']) {
    case 'deploy':
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
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "*Action:*\nCaches cleared on <http://" . $_ENV['PANTHEON_ENVIRONMENT'] . "-" . $_ENV['PANTHEON_SITE_NAME'] . ".pantheonsite.io|" . $_ENV['PANTHEON_ENVIRONMENT'] . ">.",
                ],
            ],
        ];
        break;

    default:
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

_slack_notification( $defaults['slack_channel'], $blocks );

/**
 * Send a notification to slack
 * 
 * @param string $channel The channel to send the notification to.
 * @param string $blocks The message to send.
 */
function _slack_notification($channel, $blocks) {
    $slack_token = pantheon_get_secret('slack_deploybot_token');
    $post = [
        'channel' => $channel,
        'blocks' => $blocks,
        'text' => "Workflow notification for Pantheon site", // Fallback text for accessibility
    ];

    print("\n==== Payload Sent to Slack ====\n");
    print_r($post);

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
