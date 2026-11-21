<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // US15: jawaban chatbot disusun oleh Groq (API kompatibel OpenAI).
    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1/chat/completions'),
        'timeout' => (int) env('GROQ_TIMEOUT', 30),
    ],

    // KF-22/KF-23: vektor embedding potongan dokumen & pertanyaan chatbot.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'dimensi' => (int) env('GEMINI_EMBEDDING_DIMENSI', 768),
        'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        // Skor cosine minimum agar potongan dianggap relevan; di bawahnya → pesan di luar cakupan (KF-25).
        'ambang' => (float) env('CHATBOT_AMBANG', 0.6),
    ],

    'chatbot' => [
        'kontak' => env('CHATBOT_KONTAK', '085850854547'),
        'batas_menit' => (int) env('CHATBOT_BATAS_MENIT', 20),
        'batas_ip_menit' => (int) env('CHATBOT_BATAS_IP_MENIT', 60),
        'batas_harian' => (int) env('CHATBOT_BATAS_HARIAN', 100),
        'batas_global' => (int) env('CHATBOT_BATAS_GLOBAL', 3000),
        'cache_menit' => (int) env('CHATBOT_CACHE_MENIT', 60),
        'maks_potongan' => (int) env('CHATBOT_MAKS_POTONGAN', 200),
    ],

    // KF-19: penanda tangan sertifikat.
    'sertifikat' => [
        'kota' => env('SERTIFIKAT_KOTA', 'Surabaya'),
        'jabatan' => env('SERTIFIKAT_JABATAN', 'Direktur Universitas Telkom Kampus Surabaya'),
        'nama' => env('SERTIFIKAT_NAMA', 'DR. MOHAMMAD YANUAR HARIYAWAN, S.T., M.T.'),
        'nip' => env('SERTIFIKAT_NIP', '25760006'),
    ],

];
