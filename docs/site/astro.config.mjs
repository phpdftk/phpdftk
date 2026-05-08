// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// https://astro.build/config
export default defineConfig({
	site: 'https://phpdftk.dev',
	integrations: [
		starlight({
			title: 'phpdftk',
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/phpdftk/phpdftk' }],
			components: {
				Header: './src/components/Header.astro',
				Hero: './src/components/Hero.astro',
			},
			sidebar: [
				{ label: 'Overview', slug: 'index' },
				{
					label: 'Getting Started',
					items: [
						{ label: 'Installation', slug: 'getting-started/installation' },
						{ label: 'Quick Start', slug: 'getting-started/quick-start' },
					],
				},
				{
					label: 'Writing PDFs',
					items: [
						{ label: 'API Levels', slug: 'writing/api-levels' },
						{ label: 'Level 2: Pdf', slug: 'writing/level-2-pdf' },
						{ label: 'Level 1: PdfWriter', slug: 'writing/level-1-pdfwriter' },
						{ label: 'Level 0: PdfFileWriter', slug: 'writing/level-0-core' },
					],
				},
				{
					label: 'Reading PDFs',
					items: [
						{ label: 'PdfReader', slug: 'reading/pdf-reader' },
						{ label: 'Text Extraction', slug: 'reading/text-extraction' },
					],
				},
				{
					label: 'Toolkit',
					items: [
						{ label: 'Overview', slug: 'toolkit/overview' },
						{ label: 'Text Extractor', slug: 'toolkit/text-extractor' },
						{ label: 'Form Filler', slug: 'toolkit/form-filler' },
						{ label: 'PDF Stamper', slug: 'toolkit/pdf-stamper' },
						{ label: 'Page Slicer', slug: 'toolkit/page-slicer' },
						{ label: 'PDF Merger', slug: 'toolkit/pdf-merger' },
						{ label: 'Page Transformer', slug: 'toolkit/page-transformer' },
						{ label: 'Annotation Flattener', slug: 'toolkit/annotation-flattener' },
						{ label: 'Text Redactor', slug: 'toolkit/text-redactor' },
						{ label: 'Metadata Editor', slug: 'toolkit/metadata-editor' },
						{ label: 'PDF Encryption', slug: 'toolkit/pdf-encrypt' },
						{ label: 'Bookmark Editor', slug: 'toolkit/bookmark-editor' },
						{ label: 'Page Labeler', slug: 'toolkit/page-labeler' },
					],
				},
				{
					label: 'Conformance',
					items: [
						{ label: 'Overview', slug: 'conformance/overview' },
						{ label: 'ISO Standards', slug: 'conformance/iso-standards' },
						{ label: 'Compliance', slug: 'conformance/compliance' },
						{ label: 'Latest Report', slug: 'conformance/report' },
					],
				},
				{
					label: 'Design',
					items: [
						{ label: 'Why phpdftk?', slug: 'design/why-phpdftk' },
						{ label: 'Spec-First Design', slug: 'design/spec-first' },
						{ label: 'The Object Model', slug: 'design/object-model' },
						{ label: 'Escape Hatches', slug: 'design/escape-hatches' },
					],
				},
				{
					label: 'Reference',
					items: [
						{ label: 'Performance', slug: 'reference/performance' },
						{ label: 'Packages', slug: 'reference/packages' },
						{ label: 'Spec Coverage', slug: 'reference/spec-coverage' },
						{ label: 'Version Coverage', slug: 'reference/version-coverage' },
						{ label: 'Validation Suites', slug: 'reference/validations' },
					],
				},
			],
		}),
	],
});
