# Wabot WooCommerce Integration

## Webhook Integration

When using the webhook integration mode, the plugin will send events to your configured webhook URL at `https://woo.wabot.shop/webhook/`. Below are the details of the webhook payloads for different events.

### Difference from WooCommerce Webhooks

This webhook system is different from the standard WooCommerce webhooks:

1. **Purpose**:
   - Wabot Webhook: Specifically designed for WhatsApp integration and messaging
   - WooCommerce Webhook: General-purpose webhook system for WooCommerce events

2. **Data Format**:
   - Wabot Webhook: Optimized for WhatsApp messaging with pre-formatted data
   - WooCommerce Webhook: Raw WooCommerce data in its original format

3. **Authentication**:
   - Wabot Webhook: Uses Wabot-specific security headers
   - WooCommerce Webhook: Uses WooCommerce API authentication

4. **Events**:
   - Wabot Webhook: Includes WhatsApp-specific events and formatted data
   - WooCommerce Webhook: Limited to standard WooCommerce events

5. **Endpoint**:
   - Wabot Webhook: Fixed endpoint at `https://woo.wabot.shop/webhook/`
   - WooCommerce Webhook: Configurable endpoint in WooCommerce settings

### Webhook Events

The plugin sends the following events to your webhook:

1. **New Order**
```json
{
    "event": "new_order",
    "data": {
        "order_id": "12345",
        "customer": {
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "+1234567890"
        },
        "order": {
            "total": "99.99",
            "currency": "USD",
            "status": "pending",
            "items": [
                {
                    "product_id": "123",
                    "name": "Product Name",
                    "quantity": 2,
                    "price": "49.99"
                }
            ]
        },
        "timestamp": "2024-03-20T10:30:00Z"
    }
}
```

2. **Order Status Update**
```json
{
    "event": "order_status_update",
    "data": {
        "order_id": "12345",
        "old_status": "pending",
        "new_status": "processing",
        "customer": {
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "+1234567890"
        },
        "timestamp": "2024-03-20T11:30:00Z"
    }
}
```

3. **New User Registration**
```json
{
    "event": "new_user",
    "data": {
        "user_id": "123",
        "customer": {
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "+1234567890"
        },
        "timestamp": "2024-03-20T09:30:00Z"
    }
}
```

4. **Password Reset**
```json
{
    "event": "password_reset",
    "data": {
        "user_id": "123",
        "customer": {
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "+1234567890"
        },
        "reset_link": "https://your-site.com/reset-password?token=xyz",
        "timestamp": "2024-03-20T10:00:00Z"
    }
}
```

5. **Abandoned Cart**
```json
{
    "event": "abandoned_cart",
    "data": {
        "cart_id": "abc123",
        "customer": {
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "+1234567890"
        },
        "cart": {
            "items": [
                {
                    "product_id": "123",
                    "name": "Product Name",
                    "quantity": 2,
                    "price": "49.99"
                }
            ],
            "total": "99.99",
            "currency": "USD"
        },
        "recovery_link": "https://your-site.com/cart?token=xyz",
        "coupon_code": "RECOVER10",
        "timestamp": "2024-03-20T10:30:00Z"
    }
}
```

### Webhook Security

The webhook requests include the following headers for security:

- `X-Wabot-Signature`: HMAC-SHA256 signature of the payload
- `X-Wabot-Timestamp`: Unix timestamp of the request
- `Content-Type`: application/json

### Response Format

Your webhook endpoint should respond with:

```json
{
    "success": true,
    "message": "Event processed successfully"
}
```

### Error Handling

If your webhook endpoint fails to process the event, it should respond with:

```json
{
    "success": false,
    "error": "Error message describing what went wrong"
}
```

### Rate Limiting

- Maximum 100 requests per minute
- Maximum payload size: 1MB
- Timeout: 10 seconds

### Testing Webhook

You can test your webhook integration by:

1. Enabling webhook mode in the plugin settings
2. Creating a test order or user
3. Checking your webhook endpoint logs for incoming requests

### Best Practices

1. Always verify the `X-Wabot-Signature` header
2. Implement idempotency to handle duplicate events
3. Process webhook events asynchronously
4. Store webhook events in a queue for reliable processing
5. Implement proper error handling and logging
6. Set up monitoring for webhook failures

### Support

For any issues with webhook integration, please contact support at support@wabot.shop 