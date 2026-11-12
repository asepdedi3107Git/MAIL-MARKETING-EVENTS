<?php
/**
 * Email Templates untuk Wedding Mail Marketing
 * 5 template bawaan + 1 general + fungsi helper
 * Premium Design v2 — Fully Editable Texts
 */

class Templates
{
    // Dapatkan daftar template yang tersedia
    public static function getList()
    {
        return [
            [
                'id' => 'save_the_date',
                'name' => 'Save the Date',
                'desc' => 'Announce your wedding date elegantly.',
                'icon' => 'fa-regular fa-calendar-heart'
            ],
            [
                'id' => 'formal_invitation',
                'name' => 'Formal Invitation',
                'desc' => 'The official invitation with RSVP link.',
                'icon' => 'fa-solid fa-envelope-open-text'
            ],
            [
                'id' => 'rsvp_reminder',
                'name' => 'RSVP Reminder',
                'desc' => 'Gentle nudge for guests who haven\'t replied.',
                'icon' => 'fa-solid fa-bell'
            ],
            [
                'id' => 'week_of_details',
                'name' => 'Week-of Details',
                'desc' => 'Final details, venue map, QR code & dress code.',
                'icon' => 'fa-solid fa-clipboard-list'
            ],
            [
                'id' => 'thank_you',
                'name' => 'Thank You',
                'desc' => 'Post-wedding gratitude message.',
                'icon' => 'fa-solid fa-heart'
            ],
            [
                'id' => 'general',
                'name' => 'General Email',
                'desc' => 'Custom email for any purpose. Write your own content.',
                'icon' => 'fa-solid fa-envelope'
            ]
        ];
    }

    // Dapatkan default texts untuk setiap template type
    public static function getDefaultTexts($type)
    {
        $defaults = self::getAllDefaultTexts();
        return $defaults[$type] ?? [];
    }

    // Dapatkan SEMUA default texts untuk semua template
    public static function getAllDefaultTexts()
    {
        return [
            'save_the_date' => [
                'pre_heading' => 'Please Save the Date',
                'event_label' => '{{event_names}}',
                'sub_heading' => 'are getting married',
                'date_label' => 'The Date',
                'greeting' => 'Dear <strong>{{guest_name}}</strong>,',
                'body' => 'We are delighted to share the wonderful news — we\'re getting married!<br>Please save this date in your calendar. A formal invitation with all the details will follow soon.',
                'calendar_label' => '📅 Add to Your Calendar',
                'btn_google' => 'Google Calendar',
                'btn_apple' => 'Apple / Outlook',
                'footer_note' => 'Formal invitation to follow'
            ],
            'formal_invitation' => [
                'pre_heading' => 'You Are Cordially Invited',
                'sub_heading' => 'to the wedding celebration of',
                'event_label' => '{{event_names}}',
                'greeting' => 'Dear <strong>{{guest_name}}</strong>,',
                'body' => 'We would be truly honored by your presence as we celebrate one of the most important days of our lives.',
                'details_heading' => 'Wedding Details',
                'label_date' => '📅 Date',
                'label_time' => '🕐 Time',
                'label_venue' => '📍 Venue',
                'label_seats' => '👤 Reserved',
                'seats_suffix' => 'Seat(s)',
                'map_link_text' => '📍 View on Google Maps →',
                'rsvp_label' => 'Visit Our Website to RSVP',
                'btn_rsvp' => 'Visit Wedding Website',
                'calendar_label' => '📅 Add to Calendar',
                'btn_google' => 'Google Calendar',
                'btn_apple' => 'Apple / Outlook',
                'footer_note' => 'Kindly respond by the RSVP deadline'
            ],
            'rsvp_reminder' => [
                'greeting' => 'Dear <strong>{{guest_name}}</strong>,',
                'body_1' => 'We hope this message finds you well! We noticed we haven\'t received your RSVP yet for our wedding celebration.',
                'body_2' => 'We would absolutely love to have you there, and it would mean the world to us if you could let us know whether you can make it.',
                'reminder_title' => '⏰ Gentle Reminder',
                'reminder_body' => 'Our RSVP deadline is approaching. Please take a moment to confirm your attendance at your earliest convenience.',
                'btn_rsvp' => 'Confirm Your RSVP',
                'closing' => 'With love,',
                'event_label' => '{{event_names}}'
            ],
            'week_of_details' => [
                'greeting' => 'Dear <strong>{{guest_name}}</strong>,',
                'body' => 'The big day is almost here! Only <strong style="color:{{color_primary}};font-size:16px;">{{days_until}} days</strong> to go. Here\'s everything you need to know:',
                'details_heading' => 'Event Details',
                'label_datetime' => '📅 Date & Time',
                'label_venue' => '📍 Venue',
                'map_link_text' => 'View on Google Maps →',
                'label_seat' => '🪑 Your Seat',
                'seat_prefix' => 'Table',
                'label_dresscode' => '👔 Dress Code',
                'hashtag_heading' => '📸 Share Your Moments',
                'qr_pass_title' => 'Event Access Pass',
                'qr_heading' => 'Your Check-in QR Code',
                'qr_instruction' => 'Please present this QR code upon arrival<br>for seamless check-in.',
                'envelope_heading' => '💌',
                'envelope_title' => 'Digital Envelope',
                'closing_body' => 'We can\'t wait to celebrate with you! If you have any questions, feel free to reach out.',
                'btn_website' => 'Visit Wedding Website',
                'closing' => 'With love,',
                'event_label' => '{{event_names}}'
            ],
            'thank_you' => [
                'emoji_top' => '🕊️',
                'heading' => 'Thank You!',
                'greeting' => 'Dear <strong>{{guest_name}}</strong>,',
                'body_1' => 'Words cannot express how grateful we are that you were part of our special day. Your presence, love, and warm wishes made our celebration truly unforgettable.',
                'body_2' => 'We are overwhelmed with joy and thankful for the beautiful memories we\'ve created together.',
                'gallery_label' => '📸 Wedding Gallery',
                'gallery_text' => 'Our wedding photos will be available soon!<br>We\'ll share the exclusive gallery link once they\'re ready.',
                'envelope_heading' => '💌',
                'envelope_title' => 'Digital Envelope',
                'btn_gallery' => 'Visit Our Gallery',
                'closing' => 'With all our love,',
                'event_label' => '{{event_names}}'
            ],
            'general' => [
                'greeting' => 'Hi <strong>{{guest_name}}</strong>,',
                'body_1' => 'You are invited to attend <strong>{{event_name}}</strong>.',
                'details_heading' => 'Event Details',
                'label_date' => 'Date:',
                'label_time' => 'Time:',
                'label_location' => 'Location:',
                'body_2' => 'We would appreciate your response at your earliest convenience.',
                'body_3' => 'Please confirm your attendance by clicking below:',
                'btn_rsvp' => 'RSVP NOW',
                'body_4' => 'We look forward to hosting you.',
                'closing' => 'Warm regards,',
                'event_label' => '{{host_name}}'
            ]
        ];
    }

    // Merge custom texts with defaults
    private static function mergeTexts($type, $customTexts = [])
    {
        $defaults = self::getDefaultTexts($type);
        if (empty($customTexts) || !is_array($customTexts)) {
            return $defaults;
        }
        // Only override keys that exist in defaults
        $merged = $defaults;
        foreach ($customTexts as $key => $value) {
            if (array_key_exists($key, $defaults) && $value !== '' && $value !== null) {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }

    // Dapatkan default subject untuk setiap tipe
    public static function getDefaultSubject($type, $coupleNames = '')
    {
        $name = $coupleNames ?: 'Our Wedding';
        switch ($type) {
            case 'save_the_date':
                return "Save the Date — $name";
            case 'formal_invitation':
                return "You're Invited: $name";
            case 'rsvp_reminder':
                return "Gentle Reminder: RSVP — $name";
            case 'week_of_details':
                return "Important Details — $name Wedding";
            case 'thank_you':
                return "Thank You — $name";
            case 'general':
                return "Message from $name";
            default:
                return "From $name";
        }
    }

    // Generate HTML body untuk template
    public static function render($type, $data = [])
    {
        switch ($type) {
            case 'save_the_date':
                return self::saveTheDate($data);
            case 'formal_invitation':
                return self::formalInvitation($data);
            case 'rsvp_reminder':
                return self::rsvpReminder($data);
            case 'week_of_details':
                return self::weekOfDetails($data);
            case 'thank_you':
                return self::thankYou($data);
            case 'general':
                return self::generalEmail($data);
            default:
                return '<p>Template not found.</p>';
        }
    }

    // Helper: Decorative ornament divider
    private static function ornament($color = '#d4af37')
    {
        return '
        <div style="text-align:center;margin:32px 0;">
            <span style="display:inline-block;width:28px;height:1px;background:' . $color . ';vertical-align:middle;opacity:0.4;"></span>
            <span style="display:inline-block;margin:0 12px;font-size:14px;color:' . $color . ';vertical-align:middle;opacity:0.6;">✦</span>
            <span style="display:inline-block;width:28px;height:1px;background:' . $color . ';vertical-align:middle;opacity:0.4;"></span>
        </div>';
    }

    // Helper: CTA Button
    private static function button($text, $url, $bgColor, $style = 'filled')
    {
        if ($style === 'outline') {
            return '<a href="' . htmlspecialchars($url) . '" style="display:inline-block;border:2px solid ' . $bgColor . ';color:' . $bgColor . ';text-decoration:none;padding:14px 36px;border-radius:50px;font-weight:600;font-size:14px;letter-spacing:0.8px;text-transform:uppercase;transition:all 0.3s;">' . $text . '</a>';
        }
        return '<a href="' . htmlspecialchars($url) . '" style="display:inline-block;background:' . $bgColor . ';color:#ffffff;text-decoration:none;padding:16px 44px;border-radius:50px;font-weight:600;font-size:14px;letter-spacing:0.8px;text-transform:uppercase;box-shadow:0 4px 15px rgba(0,0,0,0.12);">' . $text . '</a>';
    }

    // ========================================================
    // 1. SAVE THE DATE
    // ========================================================
    private static function saveTheDate($d)
    {
        $primary = $d['color_primary'] ?? '#4A6741';
        $date = $d['event_date'] ?? '';
        $venue = $d['venue_name'] ?? '';
        $calLinkGoogle = $d['calendar_link_google'] ?? '#';
        $calLinkApple = $d['calendar_link_apple'] ?? '#';
        $titleFont = htmlspecialchars($d['email_title_font'] ?? 'Playfair Display');
        $titleTransform = htmlspecialchars($d['email_title_transform'] ?? 'none');
        $titleSize = htmlspecialchars($d['email_title_size'] ?? '28px');

        // Get custom or default texts
        $t = self::mergeTexts('save_the_date', $d['template_texts']['save_the_date'] ?? []);

        return '
        <div style="text-align:center;padding:20px 0;">
            <p style="font-size:11px;text-transform:uppercase;letter-spacing:5px;color:#b8a88a;margin:0 0 28px;font-weight:600;">' . $t['pre_heading'] . '</p>

            <h2 style="font-family:\'' . $titleFont . '\',Georgia,serif;font-size:' . $titleSize . ';text-transform:' . $titleTransform . ';color:#1e293b;margin:0 0 8px;font-weight:600;letter-spacing:0.5px;">' . $t['event_label'] . '</h2>
            <p style="font-size:15px;color:#94a3b8;margin:0 0 8px;font-style:italic;letter-spacing:0.5px;">' . $t['sub_heading'] . '</p>

            ' . self::ornament($primary) . '

            <div style="background:linear-gradient(145deg, #fdfcfb 0%, #f8f6f3 100%);border:1px solid rgba(0,0,0,0.06);display:inline-block;padding:28px 52px;border-radius:16px;margin:0 0 10px;position:relative;">
                <p style="margin:0 0 4px;font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:3px;font-weight:600;">' . $t['date_label'] . '</p>
                <p style="margin:0;font-size:26px;color:' . $primary . ';font-family:\'' . $titleFont . '\',Georgia,serif;font-weight:700;letter-spacing:1px;">' . htmlspecialchars($date) . '</p>
                ' . (!empty($venue) ? '<p style="margin:10px 0 0;font-size:13px;color:#94a3b8;letter-spacing:1px;">' . htmlspecialchars($venue) . '</p>' : '') . '
            </div>

            ' . self::ornament($primary) . '

            <p style="font-size:15px;color:#475569;margin:0 0 12px;">' . $t['greeting'] . '</p>
            <p style="font-size:15px;color:#64748b;line-height:1.9;margin:0 0 36px;">
                ' . $t['body'] . '
            </p>

            <div style="margin:30px 0 12px;">
                <p style="font-size:11px;color:#b8a88a;margin:0 0 16px;text-transform:uppercase;letter-spacing:2px;font-weight:600;">' . $t['calendar_label'] . '</p>
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;"><tr>
                    <td style="padding:0 6px;"><a href="' . htmlspecialchars($calLinkGoogle) . '" style="display:block;width:180px;text-align:center;background:' . $primary . ';color:#ffffff;text-decoration:none;padding:14px 0;border-radius:50px;font-weight:600;font-size:13px;letter-spacing:0.5px;box-shadow:0 4px 15px rgba(0,0,0,0.12);">' . $t['btn_google'] . '</a></td>
                    <td style="padding:0 6px;"><a href="' . htmlspecialchars($calLinkApple) . '" style="display:block;width:180px;text-align:center;border:2px solid ' . $primary . ';color:' . $primary . ';text-decoration:none;padding:12px 0;border-radius:50px;font-weight:600;font-size:13px;letter-spacing:0.5px;">' . $t['btn_apple'] . '</a></td>
                </tr></table>
            </div>

            <p style="font-size:12px;color:#cbd5e1;margin:24px 0 0;font-style:italic;">' . $t['footer_note'] . '</p>
        </div>';
    }

    // ========================================================
    // 2. FORMAL INVITATION
    // ========================================================
    private static function formalInvitation($d)
    {
        $primary = $d['color_primary'] ?? '#4A6741';
        $websiteUrl = $d['website_url'] ?? '#';
        $mapUrl = $d['venue_map_url'] ?? '';
        $seats = $d['seats_allocated'] ?? '1';
        $calLinkGoogle = $d['calendar_link_google'] ?? '#';
        $calLinkApple = $d['calendar_link_apple'] ?? '#';
        $titleFont = htmlspecialchars($d['email_title_font'] ?? 'Playfair Display');
        $titleTransform = htmlspecialchars($d['email_title_transform'] ?? 'none');
        $titleSize = htmlspecialchars($d['email_title_size'] ?? '28px');

        // Get custom or default texts
        $t = self::mergeTexts('formal_invitation', $d['template_texts']['formal_invitation'] ?? []);

        $mapSection = '';
        if (!empty($mapUrl)) {
            $mapSection = '<p style="margin:12px 0 0;"><a href="' . htmlspecialchars($mapUrl) . '" style="color:' . $primary . ';font-size:13px;text-decoration:none;font-weight:500;letter-spacing:0.3px;">' . $t['map_link_text'] . '</a></p>';
        }

        return '
        <div style="text-align:center;padding:20px 0;">
            <p style="font-size:11px;text-transform:uppercase;letter-spacing:5px;color:#b8a88a;margin:0 0 28px;font-weight:600;">' . $t['pre_heading'] . '</p>
            <p style="font-size:14px;color:#94a3b8;margin:0 0 4px;font-style:italic;">' . $t['sub_heading'] . '</p>
            <h2 style="font-family:\'' . $titleFont . '\',Georgia,serif;font-size:' . $titleSize . ';text-transform:' . $titleTransform . ';color:#1e293b;margin:0 0 8px;font-weight:600;letter-spacing:0.5px;">' . $t['event_label'] . '</h2>

            ' . self::ornament($primary) . '

            <p style="font-size:15px;color:#475569;margin:0 0 12px;">' . $t['greeting'] . '</p>
            <p style="font-size:15px;color:#64748b;line-height:1.9;margin:0 0 35px;">
                ' . $t['body'] . '
            </p>

            <!-- Event Details Card -->
            <div style="background:linear-gradient(145deg, #fdfcfb 0%, #f8f6f3 100%);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:32px 36px;margin:0 0 35px;text-align:left;">
                <p style="text-align:center;font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:3px;font-weight:600;margin:0 0 24px;">' . $t['details_heading'] . '</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid rgba(0,0,0,0.05);">
                            <span style="font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:2px;font-weight:600;display:block;margin-bottom:6px;">' . $t['label_date'] . '</span>
                            <span style="font-size:16px;color:#1e293b;font-weight:600;">{{event_date}}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid rgba(0,0,0,0.05);">
                            <span style="font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:2px;font-weight:600;display:block;margin-bottom:6px;">' . $t['label_time'] . '</span>
                            <span style="font-size:16px;color:#1e293b;font-weight:600;">{{event_time}}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid rgba(0,0,0,0.05);">
                            <span style="font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:2px;font-weight:600;display:block;margin-bottom:6px;">' . $t['label_venue'] . '</span>
                            <span style="font-size:16px;color:#1e293b;font-weight:600;display:block;">{{venue_name}}</span>
                            <span style="font-size:14px;color:#64748b;display:block;margin-top:4px;line-height:1.5;">{{venue_address}}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 0;">
                            <span style="font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:2px;font-weight:600;display:block;margin-bottom:6px;">' . $t['label_seats'] . '</span>
                            <span style="font-size:16px;color:#1e293b;font-weight:600;">' . htmlspecialchars($seats) . ' ' . $t['seats_suffix'] . '</span>
                        </td>
                    </tr>
                </table>
                ' . $mapSection . '
            </div>

            <!-- RSVP CTA -->
            <div style="margin:40px 0 24px;">
                <p style="font-size:11px;color:#b8a88a;margin:0 0 20px;text-transform:uppercase;letter-spacing:2px;font-weight:600;">' . $t['rsvp_label'] . '</p>
                ' . self::button($t['btn_rsvp'], $websiteUrl, $primary) . '
            </div>

            <div style="margin:24px 0 12px;">
                <p style="font-size:11px;color:#b8a88a;margin:0 0 14px;text-transform:uppercase;letter-spacing:2px;font-weight:600;">' . $t['calendar_label'] . '</p>
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;"><tr>
                    <td style="padding:0 6px;"><a href="' . htmlspecialchars($calLinkGoogle) . '" style="display:block;width:160px;text-align:center;border:1px solid #e2e8f0;color:#64748b;text-decoration:none;padding:10px 0;border-radius:50px;font-weight:500;font-size:12px;letter-spacing:0.5px;">' . $t['btn_google'] . '</a></td>
                    <td style="padding:0 6px;"><a href="' . htmlspecialchars($calLinkApple) . '" style="display:block;width:160px;text-align:center;border:1px solid #e2e8f0;color:#64748b;text-decoration:none;padding:10px 0;border-radius:50px;font-weight:500;font-size:12px;letter-spacing:0.5px;">' . $t['btn_apple'] . '</a></td>
                </tr></table>
            </div>

            <p style="font-size:12px;color:#cbd5e1;margin:20px 0 0;font-style:italic;">' . $t['footer_note'] . '</p>
        </div>';
    }

    // ========================================================
    // 3. RSVP REMINDER
    // ========================================================
    private static function rsvpReminder($d)
    {
        $primary = $d['color_primary'] ?? '#4A6741';
        $rsvpLink = $d['rsvp_link'] ?? '#';

        // Get custom or default texts
        $t = self::mergeTexts('rsvp_reminder', $d['template_texts']['rsvp_reminder'] ?? []);

        return '
        <div style="padding:20px 0;">
            <p style="font-size:15px;color:#475569;margin:0 0 16px;">' . $t['greeting'] . '</p>

            <p style="font-size:15px;color:#64748b;line-height:1.9;margin:0 0 16px;">
                ' . $t['body_1'] . '
            </p>

            <p style="font-size:15px;color:#64748b;line-height:1.9;margin:0 0 16px;">
                ' . $t['body_2'] . '
            </p>

            <!-- Reminder Box -->
            <div style="background:linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);border:1px solid #fde68a;border-left:4px solid #f59e0b;padding:24px 28px;border-radius:0 14px 14px 0;margin:30px 0;">
                <p style="margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#b45309;font-weight:700;">' . $t['reminder_title'] . '</p>
                <p style="margin:0;font-size:14px;color:#92400e;line-height:1.7;font-weight:500;">
                    ' . $t['reminder_body'] . '
                </p>
            </div>

            <div style="text-align:center;margin:40px 0;">
                ' . self::button($t['btn_rsvp'], $rsvpLink, $primary) . '
            </div>

            <p style="font-size:15px;color:#64748b;line-height:1.9;margin:0;">
                ' . $t['closing'] . '<br>
                <strong style="color:#1e293b;font-weight:600;display:inline-block;margin-top:8px;">' . $t['event_label'] . '</strong>
            </p>
        </div>';
    }

    // ========================================================
    // 4. WEEK-OF DETAILS
    // ========================================================
    private static function weekOfDetails($d)
    {
        $primary = $d['color_primary'] ?? '#4A6741';
        $mapUrl = $d['venue_map_url'] ?? '';
        $dressCode = $d['dress_code'] ?? '';
        $hashtag = $d['social_hashtag'] ?? '';
        $websiteUrl = $d['website_url'] ?? '#';

        // Get custom or default texts
        $t = self::mergeTexts('week_of_details', $d['template_texts']['week_of_details'] ?? []);

        $dressCodeSection = '';
        if (!empty($dressCode)) {
            $dressCodeSection = '
            <tr>
                <td style="padding:14px 0;border-bottom:1px solid rgba(0,0,0,0.05);">
                    <span style="font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:2px;font-weight:600;display:block;margin-bottom:6px;">' . $t['label_dresscode'] . '</span>
                    <span style="font-size:16px;color:#1e293b;font-weight:600;">' . htmlspecialchars($dressCode) . '</span>
                </td>
            </tr>';
        }

        $hashtagSection = '';
        if (!empty($hashtag)) {
            $hashtagSection = '
            <div style="background:linear-gradient(145deg, #fdfcfb 0%, #f8f6f3 100%);border:1px solid rgba(0,0,0,0.06);border-radius:14px;padding:20px;margin:28px 0;text-align:center;">
                <p style="margin:0 0 4px;font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:2px;font-weight:600;">' . $t['hashtag_heading'] . '</p>
                <p style="margin:6px 0 0;font-size:20px;font-weight:700;color:' . $primary . ';font-style:italic;">' . htmlspecialchars($hashtag) . '</p>
            </div>';
        }

        $qrCodeSection = '
            <!-- QR Code Check-in Box -->
            <div style="background:linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);border:2px dashed #e2e8f0;border-radius:16px;padding:36px 24px;margin:35px 0;text-align:center;">
                <p style="margin:0 0 6px;font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:3px;font-weight:700;">' . $t['qr_pass_title'] . '</p>
                <h4 style="margin:0 0 20px;font-size:18px;color:#1e293b;font-weight:600;">' . $t['qr_heading'] . '</h4>
                <div style="background-color:#f8fafc;padding:16px;border-radius:14px;display:inline-block;margin-bottom:20px;">
                    <img src="{{qr_image_url}}" alt="Check-in QR Code" width="160" height="160" style="display:block;margin:0 auto;">
                </div>
                <p style="margin:0;font-size:14px;color:#64748b;line-height:1.6;">' . $t['qr_instruction'] . '</p>
            </div>';

        $wishingWellSection = '';
        if (!empty($d['wishing_well_enable']) && !empty($d['wishing_well_details'])) {
            $wishingWellSection = '
            <div style="background:linear-gradient(145deg, #fdfcfb 0%, #f8f6f3 100%);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:28px;margin:30px 0;text-align:center;">
                <div style="font-size:28px;margin-bottom:12px;">' . $t['envelope_heading'] . '</div>
                <h4 style="margin:0 0 12px;font-size:17px;color:#1e293b;font-weight:600;">' . $t['envelope_title'] . '</h4>
                <div style="font-size:14px;color:#64748b;line-height:1.7;white-space:pre-wrap;">' . htmlspecialchars($d['wishing_well_details']) . '</div>
            </div>';
        }

        return '
        <div style="padding:20px 0;">
            <p style="font-size:15px;color:#475569;margin:0 0 16px;">' . $t['greeting'] . '</p>

            <p style="font-size:15px;color:#64748b;line-height:1.9;margin:0 0 16px;">
                ' . $t['body'] . '
            </p>

            <!-- Details Card -->
            <div style="background:linear-gradient(145deg, #fdfcfb 0%, #f8f6f3 100%);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:32px 36px;margin:35px 0;text-align:left;">
                <p style="text-align:center;font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:3px;font-weight:600;margin:0 0 24px;">' . $t['details_heading'] . '</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid rgba(0,0,0,0.05);">
                            <span style="font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:2px;font-weight:600;display:block;margin-bottom:6px;">' . $t['label_datetime'] . '</span>
                            <span style="font-size:16px;color:#1e293b;font-weight:600;">{{event_date}} at {{event_time}}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid rgba(0,0,0,0.05);">
                            <span style="font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:2px;font-weight:600;display:block;margin-bottom:6px;">' . $t['label_venue'] . '</span>
                            <span style="font-size:16px;color:#1e293b;font-weight:600;display:block;">{{venue_name}}</span>
                            <span style="font-size:14px;color:#64748b;display:block;margin-top:4px;">{{venue_address}}</span>
                            ' . (!empty($mapUrl) ? '<div style="margin-top:8px;"><a href="' . htmlspecialchars($mapUrl) . '" style="color:' . $primary . ';font-size:13px;text-decoration:none;font-weight:600;letter-spacing:0.3px;">' . $t['map_link_text'] . '</a></div>' : '') . '
                        </td>
                    </tr>
                    <!-- Table Number Section -->
                    <tr>
                        <td style="padding:14px 0;border-bottom:1px solid rgba(0,0,0,0.05);display:{{table_number_display}};">
                            <span style="font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:2px;font-weight:600;display:block;margin-bottom:6px;">' . $t['label_seat'] . '</span>
                            <span style="font-size:16px;color:#1e293b;font-weight:600;">' . $t['seat_prefix'] . ' {{table_number}}</span>
                        </td>
                    </tr>
                    ' . $dressCodeSection . '
                </table>
            </div>

            ' . $hashtagSection . '

            ' . $qrCodeSection . '

            ' . $wishingWellSection . '

            <p style="font-size:15px;color:#64748b;line-height:1.9;margin:30px 0 0;">
                ' . $t['closing_body'] . '
            </p>

            <div style="text-align:center;margin:28px 0;">
                ' . self::button($t['btn_website'], $websiteUrl, $primary) . '
            </div>

            <p style="font-size:14px;color:#64748b;">
                ' . $t['closing'] . '<br>
                <strong style="color:#1e293b;">' . $t['event_label'] . '</strong>
            </p>
        </div>';
    }

    // ========================================================
    // 5. THANK YOU
    // ========================================================
    private static function thankYou($d)
    {
        $primary = $d['color_primary'] ?? '#4A6741';
        $websiteUrl = $d['website_url'] ?? '#';
        $titleFont = htmlspecialchars($d['email_title_font'] ?? 'Playfair Display');
        $titleTransform = htmlspecialchars($d['email_title_transform'] ?? 'none');
        $titleSize = htmlspecialchars($d['email_title_size'] ?? '28px');

        // Get custom or default texts
        $t = self::mergeTexts('thank_you', $d['template_texts']['thank_you'] ?? []);

        $wishingWellSection = '';
        if (!empty($d['wishing_well_enable']) && !empty($d['wishing_well_details'])) {
            $wishingWellSection = '
            <div style="background:linear-gradient(145deg, #fdfcfb 0%, #f8f6f3 100%);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:28px;margin:35px 0 10px;text-align:center;">
                <div style="font-size:28px;margin-bottom:12px;">' . $t['envelope_heading'] . '</div>
                <h4 style="margin:0 0 12px;font-size:17px;color:#1e293b;font-weight:600;">' . $t['envelope_title'] . '</h4>
                <div style="font-size:14px;color:#64748b;line-height:1.7;white-space:pre-wrap;">' . htmlspecialchars($d['wishing_well_details']) . '</div>
            </div>';
        }

        return '
        <div style="text-align:center;padding:20px 0;">
            <p style="font-size:44px;margin:0 0 20px;">' . $t['emoji_top'] . '</p>
            <h2 style="font-family:\'' . $titleFont . '\',Georgia,serif;font-size:' . $titleSize . ';text-transform:' . $titleTransform . ';color:#1e293b;margin:0 0 8px;font-weight:600;letter-spacing:0.5px;">' . $t['heading'] . '</h2>

            ' . self::ornament($primary) . '

            <p style="font-size:15px;color:#475569;margin:0 0 16px;">' . $t['greeting'] . '</p>

            <p style="font-size:15px;color:#64748b;line-height:1.9;margin:0 0 16px;">
                ' . $t['body_1'] . '
            </p>

            <p style="font-size:15px;color:#64748b;line-height:1.9;margin:0 0 35px;">
                ' . $t['body_2'] . '
            </p>

            <div style="background:linear-gradient(145deg, #fdfcfb 0%, #f8f6f3 100%);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:32px;margin:35px 0;">
                <p style="margin:0 0 4px;font-size:11px;color:#b8a88a;text-transform:uppercase;letter-spacing:2px;font-weight:600;">' . $t['gallery_label'] . '</p>
                <p style="margin:8px 0 0;font-size:14px;color:#64748b;line-height:1.6;">' . $t['gallery_text'] . '</p>
            </div>

            ' . $wishingWellSection . '

            <div style="margin:40px 0 20px;">
                ' . self::button($t['btn_gallery'], $websiteUrl, $primary) . '
            </div>

            <p style="font-size:15px;color:#64748b;line-height:1.9;margin:30px 0 0;">
                ' . $t['closing'] . '<br>
                <strong style="font-family:\'' . $titleFont . '\',Georgia,serif;font-size:20px;color:#1e293b;font-weight:600;display:inline-block;margin-top:12px;">' . $t['event_label'] . '</strong>
            </p>
        </div>';
    }

    // ========================================================
    // 6. GENERAL EMAIL
    // ========================================================
    private static function generalEmail($d)
    {
        $primary = $d['color_primary'] ?? '#4A6741';
        $rsvpLink = $d['rsvp_link'] ?? '#';

        // Get custom or default texts
        $t = self::mergeTexts('general', $d['template_texts']['general'] ?? []);

        return '
        <div style="font-family: inherit; color: #334155;">
            <p style="margin:0 0 16px;">' . $t['greeting'] . '</p>
            
            <p style="margin:0 0 24px;">' . $t['body_1'] . '</p>
            
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0;">
                <h3 style="margin-top: 0; margin-bottom: 12px; color: #0f172a; font-size: 16px;">' . $t['details_heading'] . '</h3>
                <p style="margin: 8px 0; font-size: 15px;"><strong>' . $t['label_date'] . '</strong> {{event_date}}</p>
                <p style="margin: 8px 0; font-size: 15px;"><strong>' . $t['label_time'] . '</strong> {{event_time}}</p>
                <p style="margin: 8px 0; font-size: 15px;"><strong>' . $t['label_location'] . '</strong> {{event_location}}</p>
            </div>
            
            <p style="margin:0 0 16px;">' . $t['body_2'] . '</p>
            
            <p style="margin:0 0 24px;">' . $t['body_3'] . '</p>
            
            <div style="text-align: center; margin: 32px 0;">
                ' . self::button($t['btn_rsvp'], $rsvpLink, $primary) . '
            </div>
            
            <p style="margin:0 0 24px;">' . $t['body_4'] . '</p>
            
            <p style="margin:0;">
                ' . $t['closing'] . '<br>
                <strong>' . $t['event_label'] . '</strong>
            </p>
        </div>';
    }
}
