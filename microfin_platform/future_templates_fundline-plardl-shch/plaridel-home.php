<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Login - MicroFinance App</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: { "primary": "#136dec", "background-light": "#f6f7f8", "background-dark": "#101822" },
                    fontFamily: { "display": ["Manrope", "sans-serif"] },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 min-h-screen flex flex-col">

<header class="sticky top-0 z-50 w-full bg-white dark:bg-[#1a2634] border-b border-slate-200 dark:border-slate-700 px-4 sm:px-10 py-3">
    <div class="max-w-[1280px] mx-auto flex items-center justify-between whitespace-nowrap">
        <div class="flex items-center gap-4 text-slate-900 dark:text-white">
            <div class="size-8 text-primary">
                <svg class="w-full h-full" fill="none" viewbox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 4H17.3334V17.3334H30.6666V30.6666H44V44H4V4Z" fill="currentColor"></path>
                </svg>
            </div>
            <h2 class="text-lg font-bold leading-tight tracking-[-0.015em] hidden sm:block">MicroFinance App</h2>
        </div>
        <div class="flex flex-1 justify-end gap-4 sm:gap-8 items-center">
            <a href="register.php" class="flex min-w-[84px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 px-4 bg-primary hover:bg-blue-600 transition-colors text-white text-sm font-bold leading-normal tracking-[0.015em]">
                <span class="truncate">Register</span>
            </a>
        </div>
    </div>
</header>

<main class="flex-1 flex justify-center py-5 sm:py-10 px-4 sm:px-6">
    <div class="layout-content-container flex flex-col max-w-[1200px] w-full flex-1">
        <div class="@container">
            <div class="flex flex-col-reverse lg:flex-row gap-8 lg:gap-16 items-start justify-center">
                
                <div class="flex flex-col gap-6 w-full lg:w-1/2 lg:sticky lg:top-24">
                    <div class="w-full bg-center bg-no-repeat bg-cover rounded-xl aspect-[4/3] relative overflow-hidden group shadow-lg" 
                         style='background-image: url("https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?q=80&w=2071&auto=format&fit=crop");'>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent flex flex-col justify-end p-8 text-white">
                            <h2 class="text-3xl font-black leading-tight tracking-[-0.033em] mb-2">
                                Secure access for your financial growth
                            </h2>
                            <p class="text-base font-medium opacity-90">
                                Manage your loans, view repayment schedules, and track your application status in real-time.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-4 p-4 bg-white dark:bg-[#1a2634] rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                        <span class="material-symbols-outlined text-primary text-3xl">lock_person</span>
                        <div>
                            <h3 class="font-bold text-base dark:text-white">Bank-Grade Security</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Your data is encrypted and protected with industry standard protocols.</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col w-full lg:w-[480px] bg-white dark:bg-[#1a2634] rounded-xl shadow-md border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="p-6 sm:p-8 flex flex-col gap-6">
                        <div class="text-left">
                            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Welcome Back</h1>
                            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Please enter your details to sign in.</p>
                        </div>

                        <form method="POST" action="login.php" class="flex flex-col gap-5">
                            <label class="flex flex-col gap-2">
                                <span class="text-sm font-semibold text-slate-900 dark:text-slate-200">Email Address</span>
                                <div class="relative flex items-center">
                                    <span class="absolute left-4 text-slate-400 material-symbols-outlined text-[20px]">mail</span>
                                    <input name="email" required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white h-12 pl-11 pr-4 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-slate-400 text-sm" placeholder="user@example.com" type="email"/>
                                </div>
                            </label>

                            <label class="flex flex-col gap-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-200">Password</span>
                                </div>
                                <div class="relative flex items-center">
                                    <span class="absolute left-4 text-slate-400 material-symbols-outlined text-[20px]">lock</span>
                                    <input name="password" id="passInput" required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white h-12 pl-11 pr-12 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-slate-400 text-sm" placeholder="Enter your password" type="password"/>
                                    <button type="button" onclick="togglePass()" class="absolute right-0 h-full px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </button>
                                </div>
                                <div class="flex justify-end">
                                    <a class="text-xs font-semibold text-primary hover:text-blue-700 transition-colors" href="forgot_password.php">Forgot Password?</a>
                                </div>
                            </label>

                            <button type="submit" class="w-full h-12 bg-primary hover:bg-blue-600 text-white font-bold rounded-lg shadow-md shadow-blue-500/20 transition-all flex items-center justify-center gap-2 group">
                                <span>Log In</span>
                                <span class="material-symbols-outlined text-lg group-hover:translate-x-1 transition-transform">arrow_forward</span>
                            </button>
                        </form>

                        <div class="relative py-2">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-slate-200 dark:border-slate-700"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-white dark:bg-[#1a2634] text-slate-500">New to our platform?</span>
                            </div>
                        </div>
                        <div class="flex justify-center">
                            <a class="text-sm font-bold text-primary hover:text-blue-700 transition-colors flex items-center gap-1" href="register.php">
                                Create an account
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    function togglePass() {
        const input = document.getElementById('passInput');
        input.type = input.type === 'password' ? 'text' : 'password';
    }
</script>

</body>
</html>