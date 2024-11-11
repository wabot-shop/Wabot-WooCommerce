# Wabot-WooCommerce

Wabot-WooCommerce is a WooCommerce plugin that integrates Wabot’s WhatsApp messaging capabilities directly into your WooCommerce store. Use this plugin to automatically send personalized WhatsApp notifications to customers for key order events, such as order confirmations, shipping updates, and abandoned carts, to improve engagement and reduce cart abandonment rates.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Setting Up Notification Templates](#setting-up-notification-templates)
  - [Enabling Abandoned Cart Recovery](#enabling-abandoned-cart-recovery)
  - [Manual and Automatic Notifications](#manual-and-automatic-notifications)
- [Frequently Asked Questions](#frequently-asked-questions)
- [License](#license)

## Features

- **Automated WhatsApp Notifications**: Notify customers automatically for various WooCommerce events like order confirmation, shipping updates, and abandoned carts.
- **Customizable Templates**: Choose and customize WhatsApp message templates for different types of notifications.
- **Abandoned Cart Recovery**: Recover lost sales with automatic reminders and customizable templates for abandoned carts.
- **Flexible Scheduling**: Set delays for sending messages after specific events (e.g., cart abandonment).
- **Guest User Support**: Capture guest emails and phone numbers to send abandoned cart reminders.
- **Detailed Dashboard**: View a list of all abandoned, pending, and recovered carts, along with performance metrics.
  
## Installation

1. **Download the Plugin**:
   - Download the `Wabot-WooCommerce` plugin from the [GitHub releases page](https://github.com/your-username/Wabot-WooCommerce/releases) or upload it manually.

2. **Upload and Activate**:
   - In your WordPress admin dashboard, go to **Plugins > Add New > Upload Plugin**.
   - Select the `Wabot-WooCommerce.zip` file and click **Install Now**.
   - Once installed, click **Activate** to enable the plugin.

3. **Wabot API Credentials**:
   - Obtain your `clientId` and `clientSecret` from your Wabot account. Go to Developer Settingd
   - Navigate to **WooCommerce > Settings > Wabot-WooCommerce** and enter your API credentials.

## Configuration

1. **Set Up Wabot API Credentials**:
   - Go to **WooCommerce > Settings > Wabot-WooCommerce**.
   - Enter your Wabot `clientId` and `clientSecret`.
   - Save changes to authenticate and connect your WooCommerce store with Wabot.

2. **Configure Notification Templates**:
   - Choose a WhatsApp template for each event type (e.g., New Order, Order Shipped, Abandoned Cart).
   - Customize the template content as needed. You can include dynamic fields like customer name, order details, and a direct cart link for abandoned carts.

3. **Enable Abandoned Cart Notifications** (optional):
   - Enable abandoned cart recovery and set the time delay for sending the first reminder.
   - Configure additional follow-up reminders as needed.
   - Customize abandoned cart messages to include coupon codes or personalized links.

## Usage

### Setting Up Notification Templates

1. Go to **WooCommerce > Settings > Wabot-WooCommerce > Notification Templates**.
2. Select a template for each type of notification (e.g., Order Confirmation, Shipping Update).
3. Customize message content and placeholders for each template.

### Enabling Abandoned Cart Recovery

1. In **Wabot-WooCommerce > Abandoned Cart Settings**, enable the **Abandoned Cart Recovery** feature.
2. Set the delay (e.g., 1 hour) for sending the first WhatsApp reminder after a cart is abandoned.
3. (Optional) Add additional follow-up reminders to increase recovery chances.

### Manual and Automatic Notifications

- **Automatic Notifications**: Notifications are automatically sent based on WooCommerce events (e.g., New Order, Shipping Update).
- **Manual Notifications**: In the **WooCommerce Orders** dashboard, you can manually trigger WhatsApp notifications for individual orders.

## Frequently Asked Questions

### Q1: Do I need a Wabot account to use this plugin?
Yes, you’ll need a Wabot account to obtain the `clientId` and `clientSecret` for API access.

### Q2: Can I customize message templates?
Yes, the plugin allows you to customize WhatsApp message templates for each type of WooCommerce event.

### Q3: Does the plugin support multi-language notifications?
Currently, the plugin supports one language at a time. Multi-language support is on the roadmap for future updates.

### Q4: Can I track the performance of abandoned cart reminders?
Yes, the plugin dashboard shows recovery metrics for abandoned carts, including open rates and click rates.

## License

This project is licensed under the [MIT License](LICENSE).
