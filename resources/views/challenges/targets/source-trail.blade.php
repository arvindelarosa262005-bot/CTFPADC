{{--
    Source Trail — internal challenge target website.

    This page is the "challenge website" a participant opens with the
    "Open Challenge Website" button. It is a plain, realistic page that
    loads a separate stylesheet and a separate script. The first of three
    flag fragments is left behind in an ordinary HTML comment, exactly as
    a developer might forget to remove it. The other two fragments live in
    the linked stylesheet and script.

    The canonical flag is intentionally NOT present here. Only this one
    fragment is.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Maple Street Community Library — books, events and study rooms for the neighbourhood.">
    <title>Maple Street Community Library</title>
    <link rel="stylesheet" href="{{ asset('challenges/source-trail/style.css') }}">
    <!--
        : strip the staging notes before launch.
        Page styling lives in style.css, behaviour lives in script.js.
        relay: p4dCTF{every
    -->
</head>
<body class="st-body">
    <header class="st-header">
        <div class="st-brand">
            <span class="st-mark" aria-hidden="true">M</span>
            <span class="st-brandname">Maple Street Library</span>
        </div>
        <nav class="st-tabs" aria-label="Main">
            <button class="st-tab is-active" type="button" data-tab="home" id="tab-home" aria-controls="panel-home" aria-selected="true">Home</button>
            <button class="st-tab" type="button" data-tab="events" id="tab-events" aria-controls="panel-events" aria-selected="false">Events</button>
            <button class="st-tab" type="button" data-tab="contact" id="tab-contact" aria-controls="panel-contact" aria-selected="false">Contact</button>
        </nav>
    </header>

    <main class="st-main">
        <section class="st-panel is-active" id="panel-home" role="tabpanel" aria-labelledby="tab-home">
            <h1>Welcome to Maple Street Library</h1>
            <p class="st-lead">Your neighbourhood library since 1974. Borrow books, join a reading circle or
            book a quiet study room.</p>

            <div class="st-cards">
                <article class="st-card">
                    <h2>Opening hours</h2>
                    <p>Mon–Fri 9:00–18:00<br>Saturday 10:00–16:00<br>Sunday closed</p>
                </article>
                <article class="st-card">
                    <h2>New this month</h2>
                    <p>Over 200 new titles in fiction, science and local history are now on the shelves.</p>
                </article>
                <article class="st-card">
                    <h2>Study rooms</h2>
                    <p>Four rooms available. Bookings open at the front desk or by phone.</p>
                </article>
            </div>
        </section>

        <section class="st-panel" id="panel-events" role="tabpanel" aria-labelledby="tab-events" hidden>
            <h1>Upcoming events</h1>
            <ul class="st-events">
                <li><strong>Tue 18:30</strong> — Mystery Book Circle</li>
                <li><strong>Thu 16:00</strong> — Kids' Story Hour</li>
                <li><strong>Sat 11:00</strong> — Intro to Family History Research</li>
            </ul>
            <p class="st-note">Events are free for members. Seats are limited.</p>
        </section>

        <section class="st-panel" id="panel-contact" role="tabpanel" aria-labelledby="tab-contact" hidden>
            <h1>Contact us</h1>
            <p>12 Maple Street, Springfield<br>Phone: 555-0142</p>
            <form class="st-form" id="contact-form" novalidate>
                <label for="contact-name">Name</label>
                <input id="contact-name" type="text" autocomplete="off">
                <label for="contact-message">Message</label>
                <textarea id="contact-message" rows="4"></textarea>
                <button class="st-submit" type="submit">Send message</button>
                <p class="st-status" id="contact-status" role="status"></p>
            </form>
        </section>
    </main>

    <footer class="st-footer">
        <span>&copy; 1974–2026 Maple Street Community Library</span>
    </footer>

    <script src="{{ asset('challenges/source-trail/script.js') }}" defer></script>
</body>
</html>
