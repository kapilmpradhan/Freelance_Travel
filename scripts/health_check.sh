#!/bin/bash

# Check if the environment argument is passed
if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <environment>"
  echo "Example: $0 staging"
  exit 1
fi

# Set environment to either 'staging' or 'production'
ENVIRONMENT="$1"
SLEEP_INTERVAL=900
SLACK_TOKEN=<slack-oauth-token>

# Define the variables for staging and production environments
if [ "$ENVIRONMENT" == "staging" ]; then
  HEALTH_CHECK_URL="https://confirmationtest02.freelancetravel.com/api/health-check/"
  SLACK_CHANNEL_ID="C086LMEU88J"
  NOTIFY_TITLE="Staging server is not responding"

elif [ "$ENVIRONMENT" == "production" ]; then
  HEALTH_CHECK_URL="https://confirmation.freelancetravel.com/api/health-check/"
  SLACK_CHANNEL_ID="C08BV4VQZFD"                   
  NOTIFY_TITLE="Production server is not responding"     

else
  echo "Invalid environment. Please specify 'staging' or 'production'."
  exit 1
fi

notify_slack() {
  # Slack API URL
  SLACK_API_URL="https://slack.com/api/chat.postMessage"

    # Construct a high-alert message with blocks
  payload=$(cat <<EOF
{
  "channel": "$SLACK_CHANNEL_ID",
  "blocks": [
    {
      "type": "section",
      "text": {
        "type": "mrkdwn",
        "text": ":rotating_light: *HIGH ALERT*: <!channel> \n\n$NOTIFY_TITLE"
      }
    },
    {
      "type": "divider"
    }
  ]
}
EOF
)

  # Send the message to Slack
  response=$(curl -s -X POST \
    -H "Authorization: Bearer $SLACK_TOKEN" \
    -H "Content-Type: application/json" \
    -d "$payload" \
    "$SLACK_API_URL")

  # Log the response
  echo "$(date): Slack message sent."
}

response_code=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_CHECK_URL")

if [ "$response_code" -ne 200 ]; then
  echo "$(date): Health check failed."
  notify_slack
fi