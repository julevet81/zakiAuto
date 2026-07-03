<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a newly created customer account to deliver their login
 * credentials (email + auto-generated password + login URL).
 *
 * CHANNELS:
 *   - mail:     Always sent if the customer has an email address.
 *   - database: Always stored so staff can resend/view later.
 *
 * OPTIONAL channels (uncomment + install the relevant package):
 *   - SMS via vonage/twilio: uncomment the 'vonage' block below and
 *     install `laravel/vonage-notification-channel`.
 *   - WhatsApp via Twilio WhatsApp API: same vonage channel but pointed
 *     at a WhatsApp-enabled Twilio number — or use a dedicated package
 *     like `laravel-notification-channels/whatsapp-business`.
 *
 * The plain-text password is passed in at construction time (the ONLY
 * moment it's available before hashing), stored transiently in this
 * value object, and never persisted to the database beyond this
 * notification's own delivery window.
 */
class CustomerAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $plainPassword  The un-hashed password — only
     *                                  available at creation time.
     * @param  string  $loginUrl       The front-end login page URL.
     */
    public function __construct(
        public readonly string $plainPassword,
        public readonly string $loginUrl = '',
    ) {
        // Resolve login URL from config if not explicitly passed.
        if ($this->loginUrl === '') {
            $this->loginUrl = config('app.frontend_url', config('app.url')) . '/login';
        }
    }

    /**
     * Determine which channels to send the notification on.
     * Mail is always attempted; add 'vonage' here once the SMS/WhatsApp
     * package is installed and configured.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        // Uncomment to add SMS/WhatsApp (requires the relevant package):
        // if (filled($notifiable->phone)) {
        //     $channels[] = 'vonage';
        // }

        return $channels;
    }

    /**
     * Mail message — sends the customer their login credentials.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تم إنشاء حسابك — بيانات الدخول')
            ->greeting('مرحباً ' . $notifiable->name . ',')
            ->line('تم إنشاء حسابك بنجاح في منظومة متابعة الطلبات.')
            ->line('بإمكانك تسجيل الدخول باستخدام البيانات التالية:')
            ->line('**البريد الإلكتروني:** ' . $notifiable->email)
            ->line('**كلمة المرور:** `' . $this->plainPassword . '`')
            ->action('تسجيل الدخول', $this->loginUrl)
            ->line('يُنصح بتغيير كلمة المرور فور تسجيل دخولك الأول.')
            ->salutation('شكراً لك');
    }

    /**
     * SMS / WhatsApp message body.
     * Uncomment the vonage() method once the channel package is installed.
     *
     * public function toVonage(object $notifiable): \Illuminate\Notifications\Messages\VonageMessage
     * {
     *     return (new \Illuminate\Notifications\Messages\VonageMessage)
     *         ->content(
     *             "مرحباً {$notifiable->name}،\n"
     *             ."تم إنشاء حسابك.\n"
     *             ."البريد: {$notifiable->email}\n"
     *             ."كلمة المرور: {$this->plainPassword}\n"
     *             ."الدخول: {$this->loginUrl}"
     *         )
     *         ->from(config('services.vonage.sms_from'));
     * }
     */

    /**
     * Database representation — stored in the `notifications` table so
     * staff can see when/whether the message was delivered, and resend
     * credentials if the customer says they never received them.
     *
     * NOTE: The plain password is NOT stored here. Only a flag indicating
     * that credentials were sent, so staff can trigger a "reset password"
     * flow if needed instead.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'event'     => 'account_created',
            'email'     => $notifiable->email,
            'login_url' => $this->loginUrl,
            'sent_at'   => now()->toDateTimeString(),
        ];
    }
}
