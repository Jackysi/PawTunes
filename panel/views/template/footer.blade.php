        </section>
        <div class="footer-margin"></div>

        <!-- Scripts -->
        <script type="text/javascript" src="./assets/js/panel.min.js"></script>
        <script type="text/javascript">
            if ( typeof ( window.loadInit ) == "function" ) { window.loadInit(); }
            $( "select" ).selectbox();

            let genTime = '<?= round( ( ( microtime( true ) - ( $_SERVER[ 'REQUEST_TIME_FLOAT' ] ?? 0.0 ) ) * 1000 ), 2 ) ?>';
            console.log( `Page generated in ${genTime}ms` );
        </script>
        <link href="./assets/css/inter.css" rel="stylesheet">
    </body>
</html>