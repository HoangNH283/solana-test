const express = require('express');
const solanaWeb3 = require('@solana/web3.js');
const app = express();
const port = 3000;

app.use(express.json());

app.get('/get-balance', async (req, res) => {
    res.json({ balance:20000 });
    const address = req.query.address;
    
    if (!address) {
        return res.status(400).json({ error: 'Address is required' });
    }

    try {
        const connection = new solanaWeb3.Connection(solanaWeb3.clusterApiUrl('mainnet-beta'), 'confirmed');
        const balance = await connection.getBalance(new solanaWeb3.PublicKey(address));
        res.json({ balance });
    } catch (error) {
        res.status(500).json({ error: 'Failed to get balance' });
    }
});

app.listen(port, () => {
    console.log(`Solana balance server listening at http://localhost:${port}`);
});