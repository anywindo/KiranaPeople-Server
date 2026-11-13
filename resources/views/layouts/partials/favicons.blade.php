<!-- Favicon & App Icons -->
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ asset('favicon-dark-96x96.png') }}" media="(prefers-color-scheme: dark)">
<link rel="icon" type="image/png" sizes="96x96" href="{{ asset('favicon-light-96x96.png') }}" media="(prefers-color-scheme: light)">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-dark-32x32.png') }}" media="(prefers-color-scheme: dark)">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-light-32x32.png') }}" media="(prefers-color-scheme: light)">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-dark-16x16.png') }}" media="(prefers-color-scheme: dark)">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-light-16x16.png') }}" media="(prefers-color-scheme: light)">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Kirana People') }}">
<meta name="color-scheme" content="light dark">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">

<script>
    (function() {
        const lightFaviconUrl = "{{ asset('favicon-light-96x96.png') }}";
        const darkFaviconUrl = "{{ asset('favicon-dark-96x96.png') }}";

        function updateFavicon() {
            const htmlTag = document.documentElement;
            const explicitTheme = htmlTag.getAttribute('data-bs-theme');
            let isDark = false;

            if (explicitTheme === 'dark') {
                isDark = true;
            } else if (explicitTheme === 'light') {
                isDark = false;
            } else {
                const savedTheme = localStorage.getItem('theme');
                if (savedTheme === 'dark') {
                    isDark = true;
                } else if (savedTheme === 'light') {
                    isDark = false;
                } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    isDark = true;
                }
            }

            let activeIcon = document.getElementById('favicon-active');
            if (!activeIcon) {
                activeIcon = document.createElement('link');
                activeIcon.id = 'favicon-active';
                activeIcon.rel = 'icon';
                activeIcon.type = 'image/png';
                document.head.appendChild(activeIcon);
            }
            activeIcon.href = isDark ? darkFaviconUrl : lightFaviconUrl;
        }

        updateFavicon();

        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateFavicon);
        }

        if (window.MutationObserver) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(m) {
                    if (m.type === 'attributes' && m.attributeName === 'data-bs-theme') {
                        updateFavicon();
                    }
                });
            });
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
        }
    })();
</script>
