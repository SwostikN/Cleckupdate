<?php
// footer.php
?>
<footer class="footer">
    <div class="container">
        <div class="columns">
            <div class="column is-half1">
                <div class="footer-logo">
                    <a>
                        <img src="CleckFax_Traders_Hub_Logo_group6-removebg-preview.png" alt="Cleckfax Traders Logo" class="footer-logo-img">
                    </a>
                </div>
                <p class="title is-4">Cleckfax Traders Hub</p>
                <p>Email: <a href="mailto:info@cleckfaxtraders.com">info@cleckfaxtraders.com</a></p>
                <p>Phone: <a href="tel:+16466755074">646-675-5074</a></p>
                <p>3961 Smith Street, West Yorkshire, England</p>
                <div class="buttons mt-4">
                    <a href="https://www.facebook.com/CleckfaxTraders" class="button is-small" target="_blank">
                        <span class="icon"><i class="fab fa-facebook-f"></i></span>
                    </a>
                    <a href="https://www.twitter.com/CleckfaxTraders" class="button is-small" target="_blank">
                        <span class="icon"><i class="fab fa-twitter"></i></span>
                    </a>
                    <a href="https://www.instagram.com/CleckfaxTraders" class="button is-small" target="_blank">
                        <span class="icon"><i class="fab fa-instagram"></i></span>
                    </a>
                </div>
            </div>
            <div class="column is-half">
                <h2 class="title is-4" style="margin-left: -70px;">Contact Us</h2>
                <form method="post" action="/contact">
                    <div class="field">
                        <label class="label" for="name">Name</label>
                        <div class="control">
                            <input class="input" type="text" id="name" name="name" placeholder="Name" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="email">Email</label>
                        <div class="control">
                            <input class="input" type="email" id="email" name="email" placeholder="Email" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="message">Message</label>
                        <div class="control">
                            <textarea class="textarea" id="message" name="message" placeholder="Type your message here..." required></textarea>
                        </div>
                    </div>
                    <div class="field">
                        <div class="control">
                            <button class="button is-primary" type="submit">Send</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</footer>

<style>
/* Footer container */
.footer {
    background-color: #f0f0f0;
    padding: 1rem 0;
    font-size: 0.9rem;
}

/* Container and columns spacing */
.footer .container {
    max-width: 900px;
    margin: 0 auto;
}

.footer .columns {
    margin-left: 0;
    margin-right: 0;
}

.footer .column {
    padding: 0.5rem;
}

/* Logo size */
.footer-logo-img {
    width: 250px;
    height: auto;
    display: block;
    margin-bottom: 0.5rem;
}

/* Social media buttons */
.footer .buttons {
    margin-top: 0.75rem;
}

.footer .button {
    padding: 0.3rem 0.5rem;
    font-size: 0.8rem;
    margin-right: 0.25rem;
}

/* Form spacing */
.footer form .field {
    margin-bottom: 0.75rem;
}

.footer input.input,
.footer textarea.textarea {
    font-size: 0.85rem;
    padding: 0.5rem;
}

.column.is-half1 {
    margin-left: -50px;
}
</style>