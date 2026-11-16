# PDM-Templates

## AI page generation

**Templates → Generate Page** builds page content from a chosen template using Gemini.

- Company context: the site title (or the PDM knowledge base `Business / Company Name`) plus any locations from PDM Blocks, and the site's published pages for internal linking.
- Knowledge base: `PDM\Templates\AI_Page_Generator::get_knowledge_base_context()` appends the `STYLE`, `CORE`, `CONVERSION` and `OFFERINGS` categories from the PDM knowledge base (Tools → PDM Plugins → Knowledge Base) to the prompt, capped at 16,000 characters. It returns an empty string when the knowledge base is missing or unanswered, so sites without a questionnaire behave exactly as before.
- The selected writing tone still controls register; the knowledge base supplies vocabulary, banned wording and the facts the page must not contradict.
