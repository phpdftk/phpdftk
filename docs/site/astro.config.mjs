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
						{ label: 'Choose Your API', slug: 'writing/api-levels' },
						{ label: 'Pdf — High-Level Builder', slug: 'writing/level-3-pdf' },
						{ label: 'PdfDoc — Friendly Document API', slug: 'writing/level-2-pdfdoc' },
						{ label: 'PdfWriter — Object Model', slug: 'writing/level-1-pdfwriter' },
						{ label: 'PdfFileWriter — Byte-Level', slug: 'writing/level-0-core' },
					],
				},
				{
					label: 'Reading PDFs',
					items: [
						{ label: 'PdfReader', slug: 'reading/pdf-reader' },
					],
				},
				{
					label: 'Rendering HTML & SVG',
					collapsed: true,
					items: [
						{ label: 'Overview', slug: 'rendering/overview' },
						{ label: 'HTML to PDF', slug: 'rendering/html-to-pdf' },
						{ label: 'SVG to PDF', slug: 'rendering/svg-to-pdf' },
						{ label: 'phpdftk/css', slug: 'rendering/css' },
						{ label: 'phpdftk/text', slug: 'rendering/text' },
						{ label: 'phpdftk/html', slug: 'rendering/html' },
						{ label: 'phpdftk/svg', slug: 'rendering/svg' },
					],
				},
				{
					label: 'Core Object Model',
					items: [
						{ label: 'Annotations', slug: 'core/annotations' },
						{ label: 'Graphics', slug: 'core/graphics' },
						{ label: 'Fonts', slug: 'core/fonts' },
						{ label: 'Interactive', slug: 'core/interactive' },
						{ label: 'Security & Files', slug: 'core/security' },
					],
				},
				{
					label: 'Working with PDFs',
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
					label: 'Architecture',
					items: [
						{ label: 'Why phpdftk?', slug: 'design/why-phpdftk' },
						{ label: 'Spec-First Design', slug: 'design/spec-first' },
						{ label: 'The Object Model', slug: 'design/object-model' },
						{ label: 'Escape Hatches', slug: 'design/escape-hatches' },
						{ label: 'Packages', slug: 'design/packages' },
					],
				},
				{
					label: 'Standards & Performance',
					items: [
						{ label: 'Overview', slug: 'standards/overview' },
						{
							label: 'PDF Specification',
							items: [
								{ label: 'Spec Coverage', slug: 'standards/spec/coverage' },
								{ label: 'Version Coverage', slug: 'standards/spec/version-coverage' },
							],
						},
						{
							label: 'ISO Profiles',
							items: [
								{ label: 'Conformance Overview', slug: 'standards/profiles/overview' },
								{ label: 'ISO Standards', slug: 'standards/profiles/iso-standards' },
							],
						},
						{
							label: 'External Validation',
							collapsed: true,
							items: [
								{ label: 'About the Suites', slug: 'standards/validation/about' },
								{ label: 'Latest Compliance Report', slug: 'standards/validation/report' },
								{ label: 'QPDF', slug: 'standards/validation/qpdf' },
								{ label: 'Arlington PDF Model', slug: 'standards/validation/arlington' },
								{ label: 'veraPDF', slug: 'standards/validation/verapdf' },
								{ label: 'JHOVE', slug: 'standards/validation/jhove' },
								{ label: 'Apache PDFBox Preflight', slug: 'standards/validation/pdfbox-preflight' },
								{ label: 'pdfid', slug: 'standards/validation/pdfid' },
							],
						},
						{
							label: 'Performance',
							items: [
								{ label: 'Performance', slug: 'standards/performance/overview' },
								{ label: 'Latest Benchmarks', slug: 'standards/performance/benchmarks' },
							],
						},
						{ label: 'Code Coverage', slug: 'standards/coverage' },
					],
				},
				{
					label: 'API Reference',
					items: [
						{ label: 'Overview', slug: 'api-reference' },
					],
				},
			],
		}),
	],
});
