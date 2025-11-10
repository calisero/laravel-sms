<?php

/**
 * Example: Send SMS using Laravel Notifications
 *
 * This example demonstrates how to send SMS via Laravel's notification system.
 * Copy this code into your Laravel application controllers and notification classes.
 */

use Illuminate\Notifications\Notification;
use Calisero\LaravelSms\Notification\SmsMessage;

/**
 * Step 1: Create a notification class
 * 
 * Create this file: app/Notifications/WelcomeNotification.php
 */
class WelcomeNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['calisero'];
    }

    public function toCalisero($notifiable): SmsMessage
    {
        return SmsMessage::create('Welcome to our application!')
            ->from('MyApp'); // Only if sender ID is approved by Calisero
    }
}

/**
 * Step 2: Add routing method to your User model
 * 
 * In your app/Models/User.php, add this method:
 */
/*
public function routeNotificationForCalisero($notification): string
{
    return $this->phone; // Return the user's phone number in E.164 format
}
*/

/**
 * Step 3: Send the notification from your controller
 * 
 * Example usage in a controller:
 */
/*
use App\Models\User;
use App\Notifications\WelcomeNotification;

public function sendWelcomeMessage(int $userId)
{
    $user = User::find($userId);
    
    if ($user) {
        $user->notify(new WelcomeNotification());
        
        return response()->json([
            'success' => true,
            'message' => 'Welcome SMS sent successfully',
        ]);
    }
    
    return response()->json([
        'success' => false,
        'message' => 'User not found',
    ], 404);
}
*/

/**
 * Advanced Example: Notification with dynamic content
 */
class OrderConfirmationNotification extends Notification
{
    public function __construct(
        private string $orderNumber,
        private float $amount
    ) {
    }

    public function via($notifiable): array
    {
        return ['calisero'];
    }

    public function toCalisero($notifiable): SmsMessage
    {
        $message = "Order #{$this->orderNumber} confirmed! Total: {$this->amount} RON. Thank you!";
        
        return SmsMessage::create($message)
            ->from('MyStore'); // Only if approved
    }
}

/**
 * Usage with dynamic content:
 */
/*
$user = User::find($userId);
$user->notify(new OrderConfirmationNotification('ORD-12345', 99.99));
*/

echo "✓ Notification examples ready to use!\n";
echo "Copy the notification classes to app/Notifications/ and use them in your controllers.\n";


