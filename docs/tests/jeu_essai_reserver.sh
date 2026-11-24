#!/usr/bin/env bash
# Jeu d'essai « Réserver un objet » (US4), ticket #65 : docs/tests/jeu-essai-reserver.md
#
# Rejoue chaque cas par de vraies requêtes HTTP sur l'application, avec les fixtures
# fraîchement chargées, et relève pour chacun : code HTTP, message affiché, réservations
# en base, documents RESERVATION_CREEE ajoutés au journal d'audit.
#
# Environnement de développement uniquement. À lancer depuis la racine du dépôt :
#   docker compose exec php bin/console doctrine:fixtures:load -n --em=proprietaire
#   bash docs/tests/jeu_essai_reserver.sh
# Recharger les fixtures ensuite : le script crée un compte et des réservations.
set -u

B=https://localhost   # certificat local auto-signé : curl -k
D=$(mktemp -d)        # sessions (cookies) et dernière réponse
trap 'rm -rf "$D"' EXIT
# Mot de passe des comptes de test, lu dans les fixtures (valeur de test, versionnée).
MDP=$(grep -oE "MOT_DE_PASSE_TEST = '[^']+" src/DataFixtures/UtilisateurFixtures.php | sed "s/.*'//")
NOUVEL_ADHERENT=jeanne.essai@pretoutil.test

# --- Accès aux données, en lecture seule ---------------------------------------------
sql() { docker compose exec -T database sh -c "psql -tA -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\" -c \"$1\""; }
nb_journal() {
    docker compose exec -T mongodb sh -c 'mongosh --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin "$MONGO_INITDB_DATABASE" --eval "db.audit_log.countDocuments({action: \"RESERVATION_CREEE\"})"'
}
objet() { sql "SELECT id FROM objet WHERE nom LIKE '$1%'"; }
actives() { sql "SELECT count(*) FROM reservation r JOIN utilisateur u ON u.id = r.utilisateur_id WHERE u.email = '$1' AND r.statut IN ('confirmee', 'en_cours')"; }
sur_objet() { sql "SELECT count(*) FROM reservation WHERE objet_id = $1 AND statut IN ('confirmee', 'en_cours')"; }
jour() { date -v"$(printf '%+d' "$1")"d +%F; }

# --- Navigation ------------------------------------------------------------------------
page() { curl -sk -b "$D/$1" -c "$D/$1" "${@:2}"; }
champ() { grep -oE "name=\"$1\"[^>]*value=\"[^\"]+\"" | sed 's/.*value="//;s/"$//'; }
connecter() {
    rm -f "$D/$1"
    local jeton; jeton=$(page "$1" $B/connexion | champ _csrf_token)
    page "$1" -o /dev/null --data-urlencode "email=$2" --data-urlencode "mot_de_passe=$MDP" --data-urlencode "_csrf_token=$jeton" $B/connexion
}
inscrire() {
    local jeton; jeton=$(page "$1" $B/inscription | champ 'inscription\[_token\]')
    page "$1" -o /dev/null --data-urlencode 'inscription[prenom]=Jeanne' --data-urlencode 'inscription[nom]=Essai' \
        --data-urlencode "inscription[email]=$2" --data-urlencode "inscription[motDePasse]=$MDP" \
        --data-urlencode "inscription[_token]=$jeton" $B/inscription
}
jeton_reservation() { page "$1" $B/catalogue/"$2" | champ 'reservation\[_token\]'; }
# reserver session objet début fin [jeton] : affiche « code [vers chemin] »
reserver() {
    local jeton=${5-$(jeton_reservation "$1" "$2")}
    page "$1" -o "$D/reponse.html" -w '%{http_code} %{redirect_url}' \
        --data-urlencode "reservation[debut]=$3" --data-urlencode "reservation[fin]=$4" --data-urlencode "reservation[_token]=$jeton" \
        $B/catalogue/"$2"/reserver | sed -E 's# https://localhost# vers #;s/ +$//'
}
# Messages de la dernière réponse : erreurs sous les champs et bandeaux (role="alert" ou "status").
messages() {
    sed "s/&#039;/'/g;s/&quot;/\"/g" "$D/reponse.html" | tr -s ' \n' ' ' \
        | grep -oE 'role="(alert|status)"[^>]*> ?[^<]+|_erreurs" [^>]*> ?<li>[^<]+|Cet objet est hors service[^<]+' \
        | sed -E 's/role="(alert|status)"[^>]*> ?//;s/.*<li>//;s/ *$//' | paste -sd';' - | sed 's/;/ ; /g'
}
message_suivant() { page "$1" "$B$2" > "$D/reponse.html"; messages; }

# --- Relevé ----------------------------------------------------------------------------
N=0
# ligne « entrée » « attendu » « obtenu » « en base » « journal avant »
# Conforme si le code HTTP obtenu (avant la virgule) est celui attendu, et si le journal a reçu
# exactement un document RESERVATION_CREEE par réservation acceptée (303). Les messages sont
# relus à la main dans docs/tests/jeu-essai-reserver.md.
ligne() {
    N=$((N + 1))
    local journal=$(( $(nb_journal) - $5 )) verdict='Conforme'
    local acceptees; acceptees=$(grep -o 303 <<< "${2%%,*}" | wc -l | tr -d ' ')
    [[ "${3%%,*}" == "${2%%,*}" && "$journal" -eq "$acceptees" ]] || verdict='**Écart**'
    printf '| %d | %s | %s | %s | %s | +%d | %s |\n' "$N" "$1" "$2" "$3" "$4" "$journal" "$verdict"
}

BROUETTE=$(objet Brouette); TENTE=$(objet 'Tente 4'); TONDEUSE=$(objet Tondeuse); ESCABEAU=$(objet Escabeau)
MEULEUSE=$(objet Meuleuse); GLACIERE=$(objet 'Glacière'); MOTOBINEUSE=$(objet Motobineuse); PADDLE=$(objet Paddle)
RECHAUD=$(objet 'Réchaud'); RACLETTE=$(objet 'Appareil'); ROBOT=$(objet 'Robot'); BROYEUR=$(objet Broyeur); VELO=$(objet 'Vélo')

echo "Jeu d'essai rejoué le $(date '+%Y-%m-%d à %H:%M') ; J = $(date +%F)"
echo
echo '| N° | Entrée | Attendu | Obtenu | En base | Journal | Verdict |'
echo '|---|---|---|---|---|---|---|'

# Visiteur
j=$(nb_journal); r=$(reserver v "$BROUETTE" "$(jour 5)" "$(jour 6)" x)
ligne 'Visiteur, Brouette J+5 à J+6' '302 vers /connexion' "$r" "Brouette : $(sur_objet "$BROUETTE") active" "$j"

# Nouvel adhérent (0 réservation active) : les refus d'abord, pour que le quota n'intervienne pas
inscrire j "$NOUVEL_ADHERENT"; connecter j "$NOUVEL_ADHERENT"
j=$(nb_journal); r="$(reserver j "$BROUETTE" '' ''), $(messages)"
ligne 'Jeanne, Brouette, dates vides' '422, « Indiquez la date de début. » et « Indiquez la date de fin. »' "$r" "Jeanne : $(actives $NOUVEL_ADHERENT) active" "$j"
j=$(nb_journal); r="$(reserver j "$BROUETTE" "$(jour 6)" "$(jour 5)"), $(messages)"
ligne 'RG1 : Jeanne, Brouette J+6 à J+5 (fin avant début)' '422, message RG1' "$r" "Jeanne : $(actives $NOUVEL_ADHERENT) active" "$j"
j=$(nb_journal); r="$(reserver j "$BROUETTE" "$(jour -1)" "$(jour 2)"), $(messages)"
ligne 'RG1 : Jeanne, Brouette J-1 à J+2 (début passé)' '422, message RG1' "$r" "Jeanne : $(actives $NOUVEL_ADHERENT) active" "$j"
j=$(nb_journal); r="$(reserver j "$ESCABEAU" "$(jour 20)" "$(jour 34)"), $(messages)"
ligne 'RG3 : Jeanne, Escabeau J+20 à J+34 (15 jours)' '422, « Une réservation dure 14 jours au maximum. »' "$r" "Jeanne : $(actives $NOUVEL_ADHERENT) active" "$j"
j=$(nb_journal); r="$(reserver j "$TENTE" "$(jour 8)" "$(jour 10)"), $(messages)"
ligne 'RG2 : Jeanne, Tente J+8 à J+10 (Camille a J+7 à J+9)' '422, « Cet objet est déjà réservé sur une partie de cette période… »' "$r" "Tente : $(sur_objet "$TENTE") active" "$j"
j=$(nb_journal); r="$(reserver j "$TENTE" "$(jour 9)" "$(jour 11)"), $(messages)"
ligne 'RG2, borne : Jeanne, Tente J+9 à J+11 (début le jour de fin de Camille)' '422, message RG2' "$r" "Tente : $(sur_objet "$TENTE") active" "$j"
j=$(nb_journal); r="$(reserver j "$MEULEUSE" "$(jour 30)" "$(jour 31)" "$(jeton_reservation j "$BROUETTE")"), $(messages)"
ligne 'RG5 : Jeanne, Meuleuse (hors service), envoi forcé' '422, objet hors service' "$r" "Meuleuse : $(sur_objet "$MEULEUSE") active" "$j"
j=$(nb_journal); r=$(reserver j "$MOTOBINEUSE" "$(jour 30)" "$(jour 31)" "$(jeton_reservation j "$BROUETTE")")
ligne 'RG5 : Jeanne, Motobineuse (retirée du catalogue), envoi forcé' '404' "$r" "Motobineuse : $(sur_objet "$MOTOBINEUSE") active" "$j"
j=$(nb_journal); r="$(reserver j "$GLACIERE" "$(jour 30)" "$(jour 31)"), $(messages)"
ligne 'RG5 : Jeanne, Glacière (retour attendu hier, pas rendu)' '422, objet pas encore rendu' "$r" "Glacière : $(sur_objet "$GLACIERE") active" "$j"
j=$(nb_journal); r="$(reserver j "$BROUETTE" "$(jour 5)" "$(jour 6)" jeton-invente), $(messages)"
ligne 'CSRF : Jeanne, Brouette J+5 à J+6, jeton faux' '422, formulaire refusé' "$r" "Jeanne : $(actives $NOUVEL_ADHERENT) active" "$j"

# Nouvel adhérent : cas acceptés
j=$(nb_journal); r="$(reserver j "$ESCABEAU" "$(jour 20)" "$(jour 33)")"
ligne 'RG3, limite : Jeanne, Escabeau J+20 à J+33 (14 jours)' '303 vers /mes-reservations' "$r" "Jeanne : $(actives $NOUVEL_ADHERENT) active" "$j"
j=$(nb_journal); r="$(reserver j "$RECHAUD" "$(jour 0)" "$(jour 0)")"
ligne 'RG1, limite : Jeanne, Réchaud J à J (aujourd'"'"'hui, un seul jour)' '303 vers /mes-reservations' "$r" "Jeanne : $(actives $NOUVEL_ADHERENT) actives" "$j"
j=$(nb_journal); r="$(reserver j "$TONDEUSE" "$(jour 3)" "$(jour 4)"), $(message_suivant j /mes-reservations)"
ligne 'RG2 : Jeanne, Tondeuse J+3 à J+4 (période d'"'"'une réservation annulée)' '303 vers /mes-reservations, « Réservation confirmée »' "$r" "Jeanne : $(actives $NOUVEL_ADHERENT) actives" "$j"
j=$(nb_journal); r="$(reserver j "$PADDLE" "$(jour 30)" "$(jour 31)"), $(messages)"
ligne 'RG4 : Jeanne, Paddle J+30 à J+31 (4e réservation active)' '422, « Vous avez déjà 3 réservations en cours ou à venir… »' "$r" "Jeanne : $(actives $NOUVEL_ADHERENT) actives" "$j"

# Camille (2 réservations actives dans les fixtures)
connecter a adherent@pretoutil.test
jeton=$(jeton_reservation a "$BROUETTE")
j=$(nb_journal); r="$(reserver a "$BROUETTE" "$(jour 5)" "$(jour 6)" "$jeton"), $(message_suivant a /mes-reservations)"
ligne 'Cas nominal : Camille, Brouette J+5 à J+6' '303 vers /mes-reservations, « Réservation confirmée »' "$r" "Camille : $(actives adherent@pretoutil.test) actives" "$j"
j=$(nb_journal); r="$(reserver a "$BROUETTE" "$(jour 5)" "$(jour 6)" "$jeton"), $(messages)"
ligne 'Double envoi : le même formulaire renvoyé' '422, refus' "$r" "Brouette : $(sur_objet "$BROUETTE") active" "$j"

# Noah (3 réservations actives dans les fixtures)
connecter n adherent.quota@pretoutil.test
j=$(nb_journal); r="$(reserver n "$PADDLE" "$(jour 30)" "$(jour 31)"), $(messages)"
ligne 'RG4 : Noah, Paddle J+30 à J+31 (4e réservation active)' '422, message RG4' "$r" "Noah : $(actives adherent.quota@pretoutil.test) actives" "$j"

# Accès simultanés : requêtes envoyées en même temps, en arrière-plan
connecter g gestionnaire@pretoutil.test; connecter s admin@pretoutil.test
jg=$(jeton_reservation g "$VELO"); js=$(jeton_reservation s "$VELO")
j=$(nb_journal)
page g -o "$D/c1.html" -w '%{http_code}\n' --data-urlencode "reservation[debut]=$(jour 40)" --data-urlencode "reservation[fin]=$(jour 41)" --data-urlencode "reservation[_token]=$jg" $B/catalogue/"$VELO"/reserver > "$D/c1" &
page s -o "$D/c2.html" -w '%{http_code}\n' --data-urlencode "reservation[debut]=$(jour 40)" --data-urlencode "reservation[fin]=$(jour 41)" --data-urlencode "reservation[_token]=$js" $B/catalogue/"$VELO"/reserver > "$D/c2" &
wait
# Message du refusé : il dit si c'est la requête du service ou la contrainte EXCLUDE qui l'a arrêté
for c in c1 c2; do [ "$(cat "$D/$c")" = 422 ] && cp "$D/$c.html" "$D/reponse.html"; done
ligne 'RG2 simultané : Léa et Sacha, Vélo J+40 à J+41, au même instant' '303 et 422' "$(sort "$D/c1" "$D/c2" | paste -sd' ' - | sed 's/ / et /'), $(messages)" "Vélo : $(sur_objet "$VELO") active" "$j"

# Léa : 3 demandes simultanées sur 3 objets différents, alors qu'il lui reste de la place pour une ou deux
avant=$(actives gestionnaire@pretoutil.test); places=$((3 - avant))
attendu=$(for k in 1 2 3; do [ "$k" -le "$places" ] && echo 303 || echo 422; done | paste -sd' ' - | sed 's/ / et /g')
j=$(nb_journal); i=0
for o in "$RACLETTE" "$ROBOT" "$BROYEUR"; do
    i=$((i + 1)); jt=$(jeton_reservation g "$o")
    page g -o /dev/null -w '%{http_code}\n' --data-urlencode "reservation[debut]=$(jour 50)" --data-urlencode "reservation[fin]=$(jour 51)" --data-urlencode "reservation[_token]=$jt" $B/catalogue/"$o"/reserver > "$D/q$i" &
done
wait
ligne "RG4 simultané : Léa ($avant actives), 3 objets différents au même instant" "$attendu" "$(sort "$D"/q? | paste -sd' ' - | sed 's/ / et /g')" "Léa : $(actives gestionnaire@pretoutil.test) actives" "$j"
