<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FCM Push Test | Laravel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <style>body { font-family: 'Instrument Sans', sans-serif; }</style>
</head>
<body class="bg-gray-50 text-gray-900 flex items-center justify-center min-h-screen p-6">

    <div class="max-w-xl w-full bg-white border border-gray-200 shadow-xl rounded-2xl p-8 text-center">
        <div class="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
        </div>
        <h1 class="text-2xl font-bold mb-2">Laravel FCM Push Test</h1>
        <p class="text-gray-500 mb-8">Click the button below to generate your token.</p>

        <div id="token-box" class="hidden mb-6 text-left">
            <div class="flex justify-between items-end mb-1">
                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">Your Device Token:</label>
                <button id="copy-btn" class="text-[10px] font-bold text-orange-600 hover:text-orange-800 uppercase transition-colors cursor-pointer">
                    Copy Token
                </button>
            </div>
            <div id="token-text" class="p-3 bg-gray-900 text-green-400 text-[10px] font-mono rounded-lg break-all shadow-inner border border-gray-700"></div>
        </div>

        <button id="push-btn" class="w-full py-3 px-6 bg-orange-600 hover:bg-black text-white font-bold rounded-lg transition-all shadow-lg">
            Enable Push Notifications
        </button>
    </div>

    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
        import { getMessaging, getToken, onMessage } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging.js";

        const firebaseConfig = {
   apiKey: "AIzaSyC8UrfND_mKiLGpKBOF2Mh7tsjqBh55i_0",
  authDomain: "laravel-push-notificatio-e8d53.firebaseapp.com",
  projectId: "laravel-push-notificatio-e8d53",
  storageBucket: "laravel-push-notificatio-e8d53.firebasestorage.app",
  messagingSenderId: "181032135423",
  appId: "1:181032135423:web:b98c9d47666e10ecf13aca",
  measurementId: "G-F3W7DHTD83"
        };

        const app = initializeApp(firebaseConfig);
        const messaging = getMessaging(app);

        const btn = document.getElementById('push-btn');
        const tokenBox = document.getElementById('token-box');
        const tokenText = document.getElementById('token-text');
        const copyBtn = document.getElementById('copy-btn');

        // Copy Button Logic
        copyBtn.addEventListener('click', () => {
            const token = tokenText.innerText;
            if (token) {
                navigator.clipboard.writeText(token).then(() => {
                    const originalText = copyBtn.innerText;
                    copyBtn.innerText = "COPIED!";
                    copyBtn.classList.add('text-green-600');
                    copyBtn.classList.remove('text-orange-600');

                    setTimeout(() => {
                        copyBtn.innerText = originalText;
                        copyBtn.classList.remove('text-green-600');
                        copyBtn.classList.add('text-orange-600');
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                });
            }
        });

        btn.addEventListener('click', async () => {
            btn.innerText = "Processing...";
            try {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
                    const currentToken = await getToken(messaging, {
                        vapidKey: 'BBh0m8N0COnNOL4MYkjYuWwPEvhhzeAJbBILCeHv09WmTXJMgMAERMUDtq-ewlirmlOPTrr7gw6NiGPzbZVI0iU',
                        serviceWorkerRegistration: registration
                    });

                    if (currentToken) {
                        tokenBox.classList.remove('hidden');
                        tokenText.innerText = currentToken;
                        btn.innerText = "Token Generated!";
                        btn.classList.replace('bg-orange-600', 'bg-green-600');
                        console.log("FCM Token:", currentToken);
                    }
                }
            } catch (error) {
                console.error(error);
                btn.innerText = "Try Again";
            }
        });

        // Kept only console log to prevent double notifications in the foreground (when tab is open)
        onMessage(messaging, (payload) => {
            console.log('Foreground Message received: ', payload);
            // Using alert here might make it seem like double notifications, so console log is better.
        });
    </script>
</body>
</html>
