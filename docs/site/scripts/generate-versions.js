import { execSync } from 'child_process';

const tags = execSync("git tag -l 'v*' --sort=-version:refname")
	.toString()
	.trim()
	.split('\n')
	.filter(Boolean);

const versions = [
	{ label: 'latest', value: 'latest' },
	{ label: 'main', value: 'head' },
	...tags.map((tag) => ({ label: tag, value: tag })),
];

process.stdout.write(JSON.stringify(versions, null, 2) + '\n');
