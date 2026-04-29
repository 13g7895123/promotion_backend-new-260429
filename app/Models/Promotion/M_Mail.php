<?php

namespace App\Models\Promotion;

use CodeIgniter\Model;

class M_Mail extends Model
{
    public function mailJet($mail, $subject, $content)
    {
        $apiKey = '03b32dd6951a42dd27c5fe910ae35e74'; // 替換為您的 Mailjet API 金鑰
        // $apiKey = '34268a1208a721901e40eb5787e965a5'; // 替換為您的 Mailjet API 金鑰
        $apiSecret = '00a995790399e00755c6f75a1b74ef97'; // 替換為您的 Mailjet API 密鑰
        // $apiSecret = '16ca33bfc13c1439455209fa60cdf6b3'; // 替換為您的 Mailjet API 密鑰

        $emailData = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => 'cs@pcgame.tw', // 寄件者電子郵件
                        'Name' => '推廣系統' // 寄件者名稱
                    ],
                    'To' => [
                        [
                            'Email' => $mail, // 收件者電子郵件
                            // 'Name' => 'Jarvis' // 收件者名稱
                        ]
                    ],
                    'Subject' => $subject,
                    // 'TextPart' => 'This is a test email sent using Mailjet API.',
                    'HTMLPart' => $content
                ]
            ]
        ];

        $ch = curl_init('https://api.mailjet.com/v3.1/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$apiKey:$apiSecret");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // 增加錯誤處理
        if (curl_errno($ch)) {
            // echo 'cURL error: ' . curl_error($ch);
        } else {
            if ($httpCode == 200) {
                // echo 'Email sent successfully: ' . $response;
            } else {
                // echo 'Error sending email. HTTP Code: ' . $httpCode . ' Response: ' . $response;
            }
        }

        curl_close($ch);
    }
}