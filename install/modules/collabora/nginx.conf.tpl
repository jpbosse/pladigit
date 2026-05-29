# ── Collabora Online ─────────────────────────────────────────────────────────
# Inséré dans le vhost Pladigit par le rendu Nginx central quand le module
# « collabora » est actif. Ne pas éditer à la main sur le serveur : régénéré
# à partir de l'état { SSL, modules actifs }.

location ^~ /browser {
    proxy_pass         http://127.0.0.1:9980;
    proxy_set_header   Host              $http_host;
    proxy_set_header   X-Forwarded-Proto https;
    proxy_read_timeout 600s;
}

location ^~ /hosting/discovery {
    proxy_pass       http://127.0.0.1:9980;
    proxy_set_header Host              $http_host;
    proxy_set_header X-Forwarded-Proto https;
}

location ^~ /hosting/capabilities {
    proxy_pass       http://127.0.0.1:9980;
    proxy_set_header Host              $http_host;
    proxy_set_header X-Forwarded-Proto https;
}

location ^~ /cool {
    proxy_pass             http://127.0.0.1:9980;
    proxy_http_version     1.1;
    proxy_set_header       Upgrade    $http_upgrade;
    proxy_set_header       Connection "Upgrade";
    proxy_set_header       Host       $http_host;
    proxy_set_header       X-Forwarded-Proto https;
    proxy_read_timeout     36000s;
    proxy_send_timeout     36000s;
    proxy_connect_timeout  36000s;
}
