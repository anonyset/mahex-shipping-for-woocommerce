# GitHub setup — one-time step

The connected GitHub account is `anonyset`, but the current ChatGPT GitHub connector does not expose repository creation.

Create one empty **Public** repository with this exact name:

`mahex-shipping-for-woocommerce`

Recommended settings:
- Owner: `anonyset`
- Visibility: Public
- Repository name: `mahex-shipping-for-woocommerce`
- Do not initialize with a README, .gitignore or license (the prepared project already contains them).

After the empty repository exists, the prepared source tree can be committed to `main`. The first push will run:
- `Plugin quality checks`
- `Publish WordPress update channel` → creates/updates `dist`
- `Deploy documentation` → requires GitHub Pages source to be set to **GitHub Actions** once in repository Settings → Pages.

WordPress update manifest:
`https://raw.githubusercontent.com/anonyset/mahex-shipping-for-woocommerce/dist/update.json`

Update package:
`https://raw.githubusercontent.com/anonyset/mahex-shipping-for-woocommerce/dist/mahex-shipping-for-woocommerce.zip`

Documentation target:
`https://anonyset.github.io/mahex-shipping-for-woocommerce/`
