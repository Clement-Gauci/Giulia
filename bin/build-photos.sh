#!/usr/bin/env bash
#
# Prépare les photos de pizzas pour le site.
#
#   bin/build-photos.sh [dossier-source]
#
# Chaque photo source (pizza vue du dessus sur fond uni clair) produit deux fichiers
# dans assets/images/pizzas/ :
#
#   <slug>.webp       la pizza détourée sur fond transparent, 760 px (fiche pizza)
#   <slug>-sm.webp    la même en 380 px (vignettes du slider et de la carte)
#   <slug>-blur.jpg   une vignette de 48 px, saturée, qui sert de fond flouté
#
# Le nom du fichier source donne le slug de la pizza : margherita.JPG → margherita.
# Les slugs doivent correspondre à ceux dérivés de config/giulia/menu.yaml ; la table
# ALIAS ci-dessous rattrape les écarts de nommage.
#
set -euo pipefail

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source_dir="${1:-$racine/photos/sources}"
sortie="$racine/assets/images/pizzas"

# Fond uni des photos d'origine, et tolérance du détourage.
FOND_SOURCE="#FEF5D6"
FUZZ=12

# Noms de fichiers qui ne collent pas au slug de la carte.
declare -A ALIAS=(
    [quattro-fromaggi]=quattro-formaggi
    [camembert-roti-gourmand]=camembert-roti
)

command -v magick >/dev/null || { echo "ImageMagick (magick) est requis." >&2; exit 1; }
[[ -d "$source_dir" ]] || { echo "Dossier source introuvable : $source_dir" >&2; exit 1; }

mkdir -p "$sortie"
shopt -s nullglob nocaseglob
sources=("$source_dir"/*.jpg "$source_dir"/*.jpeg "$source_dir"/*.png)
shopt -u nocaseglob
[[ ${#sources[@]} -gt 0 ]] || { echo "Aucune photo dans $source_dir" >&2; exit 1; }

for src in "${sources[@]}"; do
    base="$(basename "${src%.*}")"
    slug="$(echo "$base" | tr '[:upper:]' '[:lower:]')"
    slug="${ALIAS[$slug]:-$slug}"

    # Dimensions de la source : le détourage part des quatre coins, donc il faut
    # leurs coordonnées exactes.
    read -r largeur hauteur < <(magick identify -format "%w %h\n" "$src")
    dernier_x=$((largeur - 1))
    dernier_y=$((hauteur - 1))

    # 1. Détourage du fond uni + retouche légère (saturation, contraste, netteté),
    #    puis recadrage carré centré sur la pizza.
    magick "$src" \
        -alpha set -channel RGBA -fuzz "${FUZZ}%" -fill none \
        -floodfill "+0+0" "$FOND_SOURCE" \
        -floodfill "+${dernier_x}+0" "$FOND_SOURCE" \
        -floodfill "+0+${dernier_y}" "$FOND_SOURCE" \
        -floodfill "+${dernier_x}+${dernier_y}" "$FOND_SOURCE" \
        +channel \
        -channel A -blur 0x0.8 -level 25%,75% +channel \
        -modulate 100,106,100 -sigmoidal-contrast 3x50% -unsharp 0x1+0.6+0.02 \
        -trim +repage \
        -background none -gravity center -extent "%[fx:max(w,h)]x%[fx:max(w,h)]" \
        -resize 760x760 \
        -define webp:alpha-quality=90 -quality 82 \
        "$sortie/$slug.webp"

    # 2. Version réduite pour les vignettes : une carte fait 190 px de large, inutile
    #    d'y télécharger la photo pleine taille.
    magick "$sortie/$slug.webp" -resize 380x380 \
        -define webp:alpha-quality=90 -quality 80 \
        "$sortie/$slug-sm.webp"

    # 3. Vignette de fond : un carré pris au cœur de la garniture, saturé et réduit à
    #    32 px. Étirée en CSS, elle devient un halo aux couleurs du plat pour un poids
    #    dérisoire. Le carré reste sous 50 % : au-delà, ses coins sortent du disque et
    #    le halo dessine en grand la silhouette de la pizza — un cadre bien visible
    #    derrière la photo nette.
    magick "$sortie/$slug.webp" \
        -background "#100e0c" -alpha remove \
        -gravity center -crop 46%x46%+0+0 +repage \
        -resize 32x32! -modulate 88,148,100 -brightness-contrast -10x8 \
        -quality 82 \
        "$sortie/$slug-blur.jpg"

    printf '%-20s %4s Ko · %3s Ko · %s Ko\n' "$slug" \
        "$(( $(stat -c%s "$sortie/$slug.webp") / 1024 ))" \
        "$(( $(stat -c%s "$sortie/$slug-sm.webp") / 1024 ))" \
        "$(( $(stat -c%s "$sortie/$slug-blur.jpg") / 1024 ))"
done

echo
echo "→ $sortie"
echo "Pense à déclarer chaque photo dans config/giulia/menu.yaml (clé « photo »)."
